<?php

namespace LowListTests;

class NavigationTest extends TestCase
{
    use NativeProbe;

    /**
     * Exercise every public modern navigation flow through an ordinary provider.
     *
     * @return void
     */
    public function testModernNavigationProvider(): void
    {
        $result = $this->navigation('modern');
        $this->assertSame(array('controller', 'direct', 'active_identity', 'alias', 'automatic_index', 'slash_alias', 'empty_alias', 'explicit_shortname', 'unmatched', 'false_defaults', 'null_defaults', 'empty_title', 'null_title', 'alias_overrides_direct', 'deferred_before', 'deferred_after', 'manual_post_events', 'toolbar_default', 'toolbar_custom', 'toolbar_empty_title', 'urls'), array_keys($result));
        $this->assertSame('2.2.0', $result['controller']['version']);
        $this->assertSame('LowList\\Conduit\\McpNav', $result['controller']['class']);
        $this->assertPage($result['controller']['nav'], array(), null, null, array());
        $items = array(
            'index' => array('text' => 'Home', 'url' => '/cp/addons/settings/low_list/index', 'button' => null, 'active' => ''),
            'history' => array('text' => 'History', 'url' => '/cp/addons/settings/low_list/history', 'button' => null, 'active' => ''),
            'HEADING-tools' => array('text' => 'Tools', 'url' => null, 'button' => null, 'active' => ''),
            'https://docs.example.test' => array('text' => 'Docs', 'url' => 'https://docs.example.test', 'button' => null, 'active' => ''),
            'license' => array('text' => 'My license', 'url' => '/cp/addons/settings/low_list/license', 'button' => null, 'active' => ''),
        );
        $direct = $items;
        $direct['history']['active'] = 'active active ';
        $direct['history']['button'] = array('text' => 'Import', 'url' => '/cp/addons/settings/low_list/import');
        $this->assertPage($result['direct'], $direct, 'history', 'History');
        $alias = $direct;
        $alias['history']['active'] = 'active ';
        $this->assertPage($result['alias'], $alias, 'history', 'History');
        $this->assertTrue($result['active_identity']);
        foreach (array('automatic_index' => 'Empty-key home', 'slash_alias' => 'Slash home', 'empty_alias' => 'Index') as $case => $title) {
            $home = array('index' => array('text' => $title, 'url' => '/cp/addons/settings/low_list/index', 'button' => null, 'active' => 'active '));
            $this->assertPage($result[$case], $home, 'index', $title);
        }
        $activeHistory = $items;
        $activeHistory['history']['active'] = 'active ';
        $this->assertPage($result['explicit_shortname'], $activeHistory, 'history', 'History');
        $this->assertPage($result['unmatched'], $items, null, null);
        $this->assertPage($result['false_defaults'], array(), null, null);
        $this->assertPage($result['null_defaults'], array(), null, null);
        $this->assertPage($result['empty_title'], array('history' => array('text' => '', 'url' => '/cp/addons/settings/low_list/history', 'button' => null, 'active' => 'active active ')), 'history', '');
        $this->assertPage($result['null_title'], array('history' => array('text' => null, 'url' => '/cp/addons/settings/low_list/history', 'button' => null, 'active' => 'active active ')), 'history', null);
        $both = array_intersect_key($items, array('index' => true, 'history' => true));
        $both['index']['active'] = 'active ';
        $both['history']['active'] = 'active active ';
        $this->assertPage($result['alias_overrides_direct'], $both, 'index', 'Home');
        $this->assertSame(array('sidebar' => false, 'events' => array(array('pre', false)), 'active' => null), $result['deferred_before']);
        $activeHistory['history']['active'] = 'active active ';
        $this->assertPage($result['deferred_after'], $activeHistory, 'history', 'History', array(array('pre', false)));
        $this->assertSame(array(array('pre', false), array('post', true)), $result['manual_post_events']);
        $this->assertSame(array('title' => 'Low List', 'toolbar_items' => array('settings' => array('href' => '/cp/addons/settings/low_list/settings', 'title' => 'Low List'))), $result['toolbar_default']);
        $this->assertSame(array('title' => 'Documentation', 'toolbar_items' => array('help' => array('href' => 'https://docs.example.test', 'title' => 'Documentation'))), $result['toolbar_custom']);
        $this->assertSame(array('title' => 'Low List', 'toolbar_items' => array('home' => array('href' => '/cp/addons/settings/low_list/index', 'title' => 'Low List'))), $result['toolbar_empty_title']);
        $this->assertSame(array('/cp/addons/settings/low_list/index', '/cp/addons/settings/low_list/index', '/cp/addons/settings/low_list', 'https://docs.example.test'), $result['urls']);
    }

    /**
     * Delegate the legacy menu to EE2 without constructing a modern sidebar.
     *
     * @return void
     */
    public function testLegacyNavigationProvider(): void
    {
        $result = $this->navigation('legacy');
        $base = 'https://admin.example.test/index.php?&amp;C=addons_modules&amp;M=show_module_cp&amp;module=low_list&amp;method=';
        $this->assertSame(array('Home' => $base . 'index&amp;D=cp', 'History' => $base . 'history&amp;D=cp', 'Docs' => 'https://docs.example.test'), $result['nav']);
        $this->assertSame(array(array('pre', false), array('post', false)), $result['events']);
        $this->assertFalse($result['sidebar']);
        $this->assertNull($result['active']);
    }

    /**
     * Keep an ordinary controller method containing http inside the control panel.
     *
     * @return void
     */
    public function testInternalHttpNamedRouteGetsModuleUrl(): void
    {
        $this->assertSame(array('url' => '/cp/addons/settings/low_list/http_logs'), $this->navigation('http-route'));
    }

    /**
     * Assert complete observable page state, including absent metadata.
     *
     * @param array $actual
     * @param array $items
     * @param string|null $active
     * @param string|null $title
     * @param array $events
     * @return void
     */
    private function assertPage(array $actual, array $items, $active, $title, array $events = array(array('pre', false), array('post', true)))
    {
        $breadcrumbs = $title === null ? array() : array(array('/cp/addons/settings/low_list/index', 'Low List'));
        $breadcrumbMap = $title === null ? array() : array('/cp/addons/settings/low_list/index' => 'Low List');
        $this->assertSame(array('items' => $items, 'active' => $active, 'title' => $title, 'breadcrumbs' => $breadcrumbs, 'breadcrumb_map' => $breadcrumbMap, 'events' => $events), $actual);
    }

    /**
     * Require clean native completion and removal of the owned installation layout.
     *
     * @param string $mode
     * @return array
     */
    private function navigation($mode)
    {
        $before = (array) glob(PATH_CACHE . 'low-list-navigation-*');
        list($status, $output, $errors) = $this->probe(array('navigation', $mode));
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertSame($before, (array) glob(PATH_CACHE . 'low-list-navigation-*'));
        $result = json_decode($output, true);
        $this->assertIsArray($result, $output);

        return $result;
    }
}
