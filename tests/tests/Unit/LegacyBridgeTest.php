<?php

namespace LowListTests;

use LowList\FluxCapacitor\Bridge\EE2;

class LegacyBridgeTest extends TestCase
{
    /**
     * Keep legacy routes and explicit method/query values intact.
     *
     * @dataProvider routes
     * @param string $path
     * @param string $mode
     * @param array $variables
     * @param string $expected
     * @return void
     */
    public function testControlPanelRoutes($path, $mode, array $variables, $expected): void
    {
        $bridge = new EE2(array('module' => 'low_list', 'module_name' => 'Low List'));

        $this->assertSame(BASE . AMP . 'D=cp' . AMP . $expected, $bridge->cpURL($path, $mode, $variables));
    }

    /**
     * Supply every legacy route translation plus an ordinary route.
     *
     * @return array
     */
    public static function routes(): array
    {
        return array(
            array('listing', 'unused', array('filter_by_channel' => 4), 'C=content_edit&amp;channel_id=4'),
            array('publish', 'create', array('channel_id' => 4), 'C=content_publish&amp;M=entry_form&amp;channel_id=4'),
            array('publish', 'edit', array(), 'C=content_publish&amp;M=entry_form'),
            array('publish', 'other', array(), 'C=content_publish&amp;M=other'),
            array('members', 'groups', array(), 'C=members&amp;M=member_group_manager'),
            array('members', 'view', array(), 'C=members&amp;M=view'),
            array('channels', 'create', array(), 'C=admin_content&amp;M=channel_add'),
            array('channels', '', array(), 'C=admin_content'),
            array('addons', '', array(), 'C=addons_modules'),
            array('custom', 'details', array('id' => 5), 'C=custom&amp;M=details&amp;id=5'),
        );
    }

    /**
     * Include session identifiers only when absent from an existing base URL.
     *
     * @return void
     */
    public function testBaseUrlsAndSessionModes(): void
    {
        $bridge = new EE2(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->session = new Session();
        $prefix = BASE . AMP . 'C=addons_modules' . AMP . 'M=show_module_cp' . AMP . 'module=low_list';
        $this->assertSame($prefix . AMP . 'D=cp', $bridge->getBaseURL('/'));
        ee()->config->items['cp_session_type'] = 's';
        ee()->session->values['session_id'] = 'session';
        $this->assertSame($prefix . AMP . 'method=license' . AMP . 'S=session' . AMP . 'D=cp', $bridge->getBaseURL('license'));
        ee()->config->items['cp_session_type'] = 'cs';
        ee()->session->values['fingerprint'] = 'fingerprint';
        $this->assertSame($prefix . AMP . 'S=existing' . AMP . 'D=cp', $bridge->getBaseURL('', AMP . 'S=existing' . AMP . 'D=cp'));
        $this->assertStringContainsString(AMP . 'S=fingerprint', $bridge->getBaseURL());
        $this->assertStringEndsWith(AMP . 'method=license' . AMP . 'id=3', $bridge->moduleURL('license', array('id' => 3)));
        $this->assertStringEndsWith(AMP . 'method=index', $bridge->moduleURL('index', null));
    }
}
