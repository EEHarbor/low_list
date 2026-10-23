<?php

namespace LowListTests;

use LowList\FluxCapacitor\Conduit\Version;

class VersionPayloadTest extends TestCase
{
    /**
     * Preserve site, license and version values at the real encryption boundary.
     *
     * @dataProvider siteLicenses
     * @param mixed $siteLicense
     * @param mixed $expected
     * @return void
     */
    public function testPayloadBusinessFields($siteLicense, $expected): void
    {
        $version = $this->version();
        ee()->config->items['site_id'] = 9;
        ee()->config->items['base_url'] = 'https://example.test/subdirectory/';
        ee()->config->items['site_license_key'] = $siteLicense;

        $this->assertNull($version->check());
        $this->assertCount(1, $GLOBALS['low_list_encryptions']);
        $event = $GLOBALS['low_list_encryptions'][0];
        $this->assertTrue($event['success']);
        $this->assertSame(array(
            'api' => '1', 'l' => 'ABC-123', 'a' => 'low_list', 'v' => '2.2.0',
            'e' => APP_VER, 'p' => PHP_VERSION, 'd' => 'https://example.test/subdirectory/',
            'c' => false, 's' => 9, 'pi' => 0, 'pl' => $expected, 'lc' => false,
        ), json_decode($event['data'], true));
        $this->assertCount(1, ee()->cp->footer);
        $this->assertMatchesRegularExpression('/\?[0-9a-f]{1024}/', ee()->cp->footer[0]);
        $this->assertStringNotContainsString('ABC-123', ee()->cp->footer[0]);
    }

    /**
     * Supply absent and configured optional EE site licenses.
     *
     * @return array
     */
    public static function siteLicenses(): array
    {
        return array(array(false, 0), array('site-license', 'site-license'));
    }

    /**
     * Report the installed Pro version rather than falsely identifying it as absent.
     *
     * @return void
     */
    public function testInstalledProVersionIsIncluded(): void
    {
        $version = $this->version();
        ee()->db->results['modules'] = array(array('module_version' => '2.3.0'));
        $version->check();

        $this->assertSame('2.3.0', $version->pro->module_version);
        $this->assertSame('2.3.0', json_decode($GLOBALS['low_list_encryptions'][0]['data'], true)['pi']);
    }

    /**
     * Carry cached schema validity into every instance's encrypted update payload.
     *
     * @return void
     */
    public function testCachedValidityIsIncludedInEveryPayload(): void
    {
        $first = $this->version();
        $first->check();
        $second = new Version();
        $second->check();

        $this->assertCount(2, $GLOBALS['low_list_encryptions']);
        $this->assertFalse(json_decode($GLOBALS['low_list_encryptions'][0]['data'], true)['c']);
        $this->assertFalse(json_decode($GLOBALS['low_list_encryptions'][1]['data'], true)['c']);
    }

    /**
     * Avoid publishing an empty encrypted pixel when a valid long base URL exceeds RSA capacity.
     *
     * @return void
     */
    public function testOversizedPayloadDoesNotPublishEmptyCiphertext(): void
    {
        $version = $this->version();
        ee()->config->items['base_url'] = 'https://example.test/' . str_repeat('path/', 250);
        $version->check();

        $this->assertCount(1, $GLOBALS['low_list_encryptions']);
        $this->assertFalse($GLOBALS['low_list_encryptions'][0]['success']);
        $this->assertSame(array(), ee()->cp->footer);
    }

    /**
     * Supply a valid schema, a sanitized license and an uninstalled Pro module.
     *
     * @return Version
     */
    private function version()
    {
        ee()->db->tables = array('eeharbor_licenses');
        ee()->db->results["SHOW KEYS FROM exp_eeharbor_licenses WHERE Key_name = 'PRIMARY'"] = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
        ee()->db->results['eeharbor_licenses'] = array(array('license_key' => ' ABC-123! '));
        ee()->db->results['modules'] = array();
        ee()->cp = new ControlPanel();

        return new Version();
    }
}
