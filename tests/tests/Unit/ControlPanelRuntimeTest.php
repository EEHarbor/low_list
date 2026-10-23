<?php

namespace LowListTests;

class ControlPanelRuntimeTest extends TestCase
{
    use NativeProbe;

    /**
     * Return each fresh constructor and sequential singleton context.
     *
     * @return array
     */
    public static function constructors()
    {
        return array_map(static function ($case) { return array($case); }, array('fieldtype-modern', 'fieldtype-legacy', 'tab-modern', 'tab-legacy', 'shared-modern', 'shared-legacy'));
    }

    /**
     * Construct the real CP parent, encrypt real payloads and preserve prior markup.
     *
     * @dataProvider constructors
     * @param string $case
     * @return void
     */
    public function testConstructorsKeepValidSchemaAndOnePixel($case): void
    {
        $result = $this->runContext($case);
        $count = strpos($case, 'shared-') === 0 ? 2 : 1;
        $this->assertSame(array_fill(0, $count, '2.2.0'), $result['versions']);
        $bridge = strpos($case, '-legacy') === false ? 'EE6' : 'EE2';
        $this->assertSame(array_fill(0, $count, 'LowList\\FluxCapacitor\\Bridge\\' . $bridge), $result['bridges']);
        $this->assertSame(strpos($case, 'tab-') === 0 ? null : 'channel', $result['content_type']);
        $this->assertTrue($result['footer_alias']);
        $this->assertCount(2, $result['footer']);
        $this->assertSame('<footer>existing</footer>', $result['footer'][0]);
        $this->assertStringStartsWith('<div id="low_list__up"', $result['footer'][1]);
        $this->assertCount($count, $result['encryptions']);
        foreach ($result['encryptions'] as $encryption) {
            $this->assertTrue($encryption['success']);
            $this->assertIsArray($encryption['payload']);
            $this->assertFalse($encryption['payload']['c']);
        }
    }

    /**
     * Return original and legacy CP menu request contexts.
     *
     * @return array
     */
    public static function menus()
    {
        return array(array('menu-modern'), array('menu-legacy'));
    }

    /**
     * Forward both hook names with exact configured title and native bridge URL shape.
     *
     * @dataProvider menus
     * @param string $case
     * @return void
     */
    public function testMenuHooksForwardTitleAndUrl($case): void
    {
        $result = $this->runContext($case);
        $url = $case === 'menu-modern' ? '/cp/addons/settings/low_list/index' : BASE . '&amp;C=addons_modules&amp;M=show_module_cp&amp;module=low_list&amp;D=cp&amp;method=index';
        $item = array('Low List', $url, $case === 'menu-modern');
        $this->assertSame(array(null, null), $result['returns']);
        $this->assertSame(array($item, $item), $result['items']);
    }

    /**
     * Return present, empty and absent optional provider installations.
     *
     * @return array
     */
    public static function providers()
    {
        return array(array('updater-present'), array('updater-empty'), array('updater-missing'));
    }

    /**
     * Load optional providers once and retain exact extension-update write scopes.
     *
     * @dataProvider providers
     * @param string $case
     * @return void
     */
    public function testUpdaterLoadsOptionalProvider($case): void
    {
        try {
            $result = $this->runContext($case);
        } finally {
            $this->assertSame(array(), glob(PATH_CACHE . '/low-list-updater-*'));
        }
        $this->assertTrue($result['result']);
        $this->assertSame('Low_list', $result['module']);
        $this->assertSame('2.2.0', $result['version']);
        $calls = $case === 'updater-present' ? array('1.0.0') : array();
        $this->assertSame($calls, $result['extension_calls']);
        $update = array('update', array('extensions', array('version' => '2.2.0'), array('class' => 'Low_list_ext')));
        $this->assertSame($case === 'updater-present' ? array($update, $update) : array(), $result['db_calls']);
    }

    /**
     * Require controlled native completion before interpreting the source response.
     *
     * @param string $case
     * @return array
     */
    private function runContext($case)
    {
        list($status, $output, $errors) = $this->probe(array('control-panel', $case));
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $result = json_decode($output, true);
        $this->assertIsArray($result, $output);

        return $result;
    }
}
