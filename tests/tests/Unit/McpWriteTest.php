<?php

namespace LowListTests;

use LowList\FluxCapacitor\Base\Mcp;

class McpWriteTest extends TestCase
{
    /**
     * Redirect the default control-panel route to its real license URL.
     *
     * @return void
     */
    public function testIndexRedirect(): void
    {
        $mcp = $this->controller();
        try {
            $mcp->index();
            $this->fail('The EE redirect must terminate controller execution.');
        } catch (Redirected $redirect) {
            $this->assertSame('/cp/addons/settings/low_list/license', $redirect->getMessage());
        }
        $this->assertSame(array('/cp/addons/settings/low_list/license'), ee()->functions->redirects);
    }

    /**
     * Sanitize license values, persist the right operation and terminate on success.
     *
     * @dataProvider successfulWrites
     * @param array $posted
     * @param bool $exists
     * @param array $expected
     * @return void
     */
    public function testSuccessfulLicenseWrites(array $posted, $exists, array $expected): void
    {
        $mcp = $this->controller();
        ee()->input->values = $posted;
        ee()->db->count = $exists ? 1 : 0;
        $row = $exists ? array(array('site_id' => 1, 'addon' => 'LowList', 'license_key' => 'CURRENT')) : array();
        ee()->db->results['eeharbor_licenses'] = $row;
        ee()->db->resultQueues['eeharbor_licenses'] = array(array());
        $this->saveUntilRedirect($mcp);

        $writes = $this->writes();
        $this->assertSame(array(array($exists ? 'update' : 'insert', array('eeharbor_licenses', $expected))), $writes);
        if ($exists) {
            $this->assertMutationScope('update');
        }
        $this->assertSame(array(ee()->db->calls), ee()->functions->databaseCallsAtRedirect);
        $this->assertContains(array('where', array('license_key', $expected['license_key'])), ee()->db->calls);
        $this->assertContains(array('withTitle', 'License Key Updated'), ee()->services['CP/Alert']->calls);
        $this->assertContains(array('asSuccess'), ee()->services['CP/Alert']->calls);
        $this->assertSame(array('/cp/addons/settings/low_list/license'), ee()->functions->redirects);
    }

    /**
     * Supply insertion, update, ignored-site, empty and exact-length boundaries.
     *
     * @return array
     */
    public static function successfulWrites(): array
    {
        return array(
            'sanitized insertion' => array(array('license_key' => ' A_b & C-1 ', 'ignore_site' => '0'), false, array('site_id' => 1, 'addon' => 'LowList', 'license_key' => 'AbC-1', 'ignore_site' => 0)),
            'update' => array(array('license_key' => 'KEY', 'ignore_site' => '0'), true, array('site_id' => 1, 'addon' => 'LowList', 'license_key' => 'KEY', 'ignore_site' => 0)),
            'clear active license' => array(array('license_key' => '', 'ignore_site' => '0'), true, array('site_id' => 1, 'addon' => 'LowList', 'license_key' => '', 'ignore_site' => 0)),
            'ignored site' => array(array('license_key' => 'unused', 'ignore_site' => '1'), false, array('site_id' => 1, 'addon' => 'LowList', 'license_key' => 'low-list-ignore-site-1', 'ignore_site' => 1)),
            'empty values' => array(array(), false, array('site_id' => 1, 'addon' => 'LowList', 'license_key' => '', 'ignore_site' => 0)),
            'null values use legacy missing fallback' => array(array('license_key' => null, 'ignore_site' => null), false, array('site_id' => 1, 'addon' => 'LowList', 'license_key' => '', 'ignore_site' => 0)),
            'zero key' => array(array('license_key' => '0', 'ignore_site' => '0'), false, array('site_id' => 1, 'addon' => 'LowList', 'license_key' => '0', 'ignore_site' => 0)),
            'maximum key length' => array(array('license_key' => str_repeat('A', 50)), false, array('site_id' => 1, 'addon' => 'LowList', 'license_key' => str_repeat('A', 50), 'ignore_site' => 0)),
        );
    }

    /**
     * Reject oversized keys before attempting a candidate lookup or write.
     *
     * @return void
     */
    public function testOversizedKeyGuard(): void
    {
        $mcp = $this->controller();
        ee()->input->values['license_key'] = str_repeat('A', 51);
        $this->saveUntilRedirect($mcp);

        $this->assertSame(array(), $this->writes());
        $this->assertContains(array('withTitle', 'License Key Too Long'), ee()->services['CP/Alert']->calls);
        $this->assertContains(array('asIssue'), ee()->services['CP/Alert']->calls);
        $this->assertNotContains(array('where', array('license_key', str_repeat('A', 51))), ee()->db->calls);
    }

    /**
     * Preserve another site's or add-on's license and show the relevant error.
     *
     * @dataProvider conflictingLicenses
     * @param array $row
     * @param array $siteRows
     * @param string $message
     * @return void
     */
    public function testConflictingLicenseGuard(array $row, array $siteRows, $message): void
    {
        $mcp = $this->controller();
        ee()->input->values = array('license_key' => 'KEY', 'ignore_site' => '0');
        ee()->db->results['eeharbor_licenses'] = array($row);
        ee()->db->results['sites'] = $siteRows;
        $this->saveUntilRedirect($mcp);

        $this->assertSame(array(), $this->writes());
        $this->assertContains(array('asIssue'), ee()->services['CP/Alert']->calls);
        $titles = array_filter(ee()->services['CP/Alert']->calls, static function ($call) { return $call[0] === 'withTitle'; });
        $this->assertCount(1, $titles);
        $this->assertStringContainsString($message, reset($titles)[1]);
    }

    /**
     * Supply real existing-record shapes for each ownership conflict.
     *
     * @return array
     */
    public static function conflictingLicenses(): array
    {
        return array(
            array(array('site_id' => 2, 'addon' => 'LowList'), array(array('site_label' => 'Other MSM Site')), 'Other MSM Site'),
            array(array('site_id' => 2, 'addon' => 'LowList'), array(), 'Could not locate MSM Site name'),
            array(array('site_id' => 1, 'addon' => 'EEHarbor\\OtherAddon'), array(), 'Other Addon'),
        );
    }

    /**
     * Remove an ignored-site record when ignoring is disabled and the key is blank.
     *
     * @return void
     */
    public function testUnignoreSiteDeletesOnlyCurrentAddonRecord(): void
    {
        $mcp = $this->controller();
        ee()->input->values = array('license_key' => '', 'ignore_site' => '0');
        ee()->db->count = 1;
        ee()->db->resultQueues['eeharbor_licenses'] = array(array(), array(array('license_key' => 'low-list-ignore-site-1')));
        $this->saveUntilRedirect($mcp);

        $this->assertSame(array(array('delete', array('eeharbor_licenses'))), $this->writes());
        $this->assertMutationScope('delete');
        $this->assertSame(array(ee()->db->calls), ee()->functions->databaseCallsAtRedirect);
        $this->assertContains(array('withTitle', 'License Key Updated'), ee()->services['CP/Alert']->calls);
    }

    /**
     * Reject structured posted keys before any license storage write.
     *
     * @return void
     */
    public function testStructuredLicenseKeyIsRejected(): void
    {
        $mcp = $this->controller();
        ee()->input->values['license_key'] = array('not-a-scalar');
        try {
            $this->expectException(PHP_VERSION_ID >= 80000 ? \TypeError::class : \ErrorException::class);
            $mcp->save_license();
        } finally {
            $this->assertSame(array(), $this->writes());
        }
    }

    /**
     * Initialize real MCP/Version dependencies with valid metadata and no I/O.
     *
     * @return Mcp
     */
    private function controller()
    {
        $mcp = new Mcp();
        ee()->functions = new Functions();
        ee()->input = new Input();
        ee()->services['CP/URL'] = new Url();
        ee()->services['CP/Alert'] = new AlertRecorder();
        ee()->db->tables = array('eeharbor_licenses');
        ee()->db->results["SHOW KEYS FROM exp_eeharbor_licenses WHERE Key_name = 'PRIMARY'"] = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
        ee()->db->calls = array();

        return $mcp;
    }

    /**
     * Execute controller work up to the native redirect control-flow boundary.
     *
     * @param Mcp $mcp
     * @return void
     */
    private function saveUntilRedirect($mcp)
    {
        try {
            $mcp->save_license();
            $this->fail('The EE redirect must terminate controller execution.');
        } catch (Redirected $redirect) {
            $this->assertSame('/cp/addons/settings/low_list/license', $redirect->getMessage());
        }
    }

    /**
     * Return only database mutations so guard and ordering assertions are explicit.
     *
     * @return array
     */
    private function writes()
    {
        return array_values(array_filter(ee()->db->calls, static function ($call) {
            return in_array($call[0], array('insert', 'update', 'delete'), true);
        }));
    }

    /**
     * Require the mutation's own query scope after preceding SELECTs have reset it.
     *
     * @param string $operation
     * @return void
     */
    private function assertMutationScope($operation)
    {
        $operations = array_column(ee()->db->calls, 0);
        $index = array_search($operation, $operations, true);
        $this->assertNotFalse($index);
        $this->assertSame(array(
            array('where', array('site_id', 1)),
            array('where', array('addon', 'LowList')),
        ), array_slice(ee()->db->calls, $index - 2, 2));
    }
}
