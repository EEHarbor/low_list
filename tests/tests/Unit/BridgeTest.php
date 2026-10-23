<?php

namespace LowListTests;

class BridgeTest extends TestCase
{
    /**
     * Verify version helpers and pagination across every shipped concrete bridge.
     *
     * @dataProvider bridgeClasses
     * @param string $class
     * @return void
     */
    public function testVersionAndPageCalculations($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->input = new Input();
        $this->assertSame('7', $bridge->getEEVersion());
        $this->assertSame('7.5.0', $bridge->getEEVersion(false));
        $this->assertTrue($bridge->ver_lt('8'));
        $this->assertFalse($bridge->ver_lt('7'));
        $this->assertTrue($bridge->ver_gt('6'));
        $this->assertFalse($bridge->ver_gt('7'));
        $this->assertTrue($bridge->ver_lte('7'));
        $this->assertFalse($bridge->ver_lte('6'));
        $this->assertTrue($bridge->ver_gte('7'));
        $this->assertFalse($bridge->ver_gte('8'));
        foreach (array('is_ee2', 'is_ee3', 'is_ee4', 'is_ee5') as $method) {
            $this->assertFalse($bridge->$method());
        }
        $this->assertSame(1, $bridge->getCurrentPage(array('per_page' => 25)));
        ee()->input->getValues['page'] = '3';
        $this->assertEquals(3, $bridge->getCurrentPage(array('per_page' => 25)));
        ee()->input->getValues['per_page'] = 50;
        $expected = substr($class, -3) === 'EE2' ? 3 : 50;
        $this->assertEquals($expected, $bridge->getCurrentPage(array('per_page' => 25)));
        $this->assertSame(50, $bridge->getStartNum(array('current_page' => 3, 'per_page' => 25)));
        $this->assertSame(0, $bridge->getStartNum(array('current_page' => 1, 'per_page' => 25)));
    }

    /**
     * Read setup metadata with one request cache and store settings by site.
     *
     * @dataProvider bridgeClasses
     * @param string $class
     * @return void
     */
    public function testSettingsPrecedenceAndPersistence($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->db->data_cache['table_names'] = array('stale');
        $this->assertSame('Low List', $bridge->getConfig('name'));
        $this->assertSame('2.2.0', $bridge->getSettings()->version);
        $this->assertSame('LowList', $bridge->getSettings(true)['namespace']);
        $bridge->setConfig('custom', 'memory');
        $this->assertSame('memory', $bridge->getConfig('custom'));
        ee()->db->tables = array('low_list_settings');
        $bridge->setConfig('custom', 0);
        $this->assertSame(array('insert', array('low_list_settings', array('site_id' => 1, 'custom' => 0))), end(ee()->db->calls));
        ee()->db->count = 1;
        $bridge->setConfig('custom', null);
        $this->assertSame(array('update', array('low_list_settings', array('site_id' => 1, 'custom' => null))), end(ee()->db->calls));
    }

    /**
     * Merge real serialized fieldtype settings, site settings and authoritative metadata.
     *
     * @dataProvider bridgeClasses
     * @param string $class
     * @return void
     */
    public function testSettingsMergePrecedence($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->db->tables = array('low_list_settings');
        ee()->db->results['low_list_settings'] = array(array('site_id' => 1, 'name' => 'overridden', 'custom' => 'database'));
        ee()->db->results['fieldtypes'] = array(array('settings' => base64_encode(serialize(array('name' => 'fieldtype', 'custom' => 'fieldtype', 'field_only' => true)))));

        $settings = $bridge->getSettings(true);

        $this->assertSame('Low List', $settings['name']);
        $this->assertSame('database', $settings['custom']);
        $this->assertTrue($settings['field_only']);
        $this->assertSame(1, $settings['site_id']);
    }

    /**
     * Build current control panel URLs without losing unused query values.
     *
     * @dataProvider modernBridgeClasses
     * @param string $class
     * @return void
     */
    public function testModernUrlsAndContext($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        $url = new Url();
        ee()->services['CP/URL'] = $url;
        $this->assertSame($url, $bridge->moduleURL('license', array('site_id' => 2)));
        $this->assertSame(array('addons/settings/low_list/license', array('site_id' => 2)), $url->last);
        $this->assertSame($url, $bridge->cpURL('listing'));
        $this->assertSame(array('publish', array()), $url->last);
        $bridge->cpURL('publish', 'create', array('channel_id' => 12, 'filter' => 'x'));
        $this->assertSame(array('publish/create/12', array('filter' => 'x')), $url->last);
        $bridge->cpURL('publish', 'edit', array('entry_id' => 99));
        $this->assertSame(array('publish/edit/entry/99', array()), $url->last);
        $bridge->cpURL('members', 'view', array('id' => 5));
        $this->assertSame(array('members/view', array('id' => 5)), $url->last);
        ee()->uri = new Uri();
        ee()->uri->path = 'cp/addons/settings/low_list/license';
        $this->assertSame('license', $bridge->getCurrentUrlInfo('method'));
        $this->assertTrue($bridge->getCurrentUrlInfo('cp'));
        $this->assertSame('low_list', $bridge->getCurrentUrlInfo('module'));
        $this->assertIsArray($bridge->getCurrentUrlInfo('unknown'));
        $this->assertSame(PATH_CACHE, $bridge->getCachePath());
        ee()->config->items['cache_path'] = '/tmp/configured-cache/';
        $this->assertSame('/tmp/configured-cache/', $bridge->getCachePath());
        $this->assertSame('/themes/user/low_list/', $bridge->getAddonThemesDir());
    }

    /**
     * An empty request path must have a safe default context.
     *
     * @dataProvider modernBridgeClasses
     * @param string $class
     * @return void
     */
    public function testEmptyModernRequestContextIsSafe($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->uri = new Uri();

        $this->assertSame(array('segments' => array(''), 'full' => '', 'cp' => false, 'module' => '', 'method' => 'index'), $bridge->getCurrentUrlInfo());
    }

    /**
     * Route persistent and session cache operations to the real EE boundaries.
     *
     * @dataProvider modernBridgeClasses
     * @param string $class
     * @return void
     */
    public function testModernCacheRouting($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->cache = new Cache();
        ee()->session = new Session();
        $this->assertFalse($bridge->cache('invalid'));
        $this->assertTrue($bridge->cache('set', 'id', array('id' => 7)));
        $this->assertSame(array('id' => 7), $bridge->cache('get', 'id'));
        $this->assertTrue($bridge->cache('delete', 'id'));
        $this->assertFalse($bridge->cache('get', 'id'));
        $this->assertTrue($bridge->cache('clear'));
        $this->assertTrue($bridge->cache('set', 'session', 'value', false));
        $this->assertSame('value', $bridge->cache('get', 'session', false, false));
        $this->assertSame(array('low_list/session' => 'value'), ee()->session->values);
    }

    /**
     * Delegate pagination, footer, URL and template parsing to EE services.
     *
     * @dataProvider modernBridgeClasses
     * @param string $class
     * @return void
     */
    public function testModernServiceDelegation($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->services['CP/URL'] = new Url();
        $this->assertSame('/cp/addons/settings/low_list', $bridge->getBaseURL('/')->compile());
        $this->assertSame('/cp/addons/settings/low_list/license?site=1', $bridge->getBaseURL('license', '?site=1')->compile());
        ee()->services['CP/Pagination'] = new Pagination();
        $url = (new Url())->make('items');
        $this->assertSame('pagination', $bridge->pagination(array('total_rows' => 100, 'per_page' => 10, 'current_page' => 2, 'base_url' => $url, 'page_links' => 3, 'page_query_string' => 'p')));
        $this->assertSame(array(array('perPage', 10), array('currentPage', 2), array('displayPageLinks', 3), array('queryStringVariable', 'p'), array('render', $url)), ee()->services['CP/Pagination']->calls);
        ee()->cp = new ControlPanel();
        $bridge->javascript_to_page('window.example = true;');
        $this->assertSame(array('<script type="text/javascript">window.example = true;</script>'), $bridge->getFooter());
        ee()->services['Variables/Parser'] = new VariableParser();
        ee()->functions = ee()->services['Variables/Parser'];
        $this->assertSame(array('parameters'), $bridge->assign_parameters('limit="5"', array('limit' => 10)));
        $this->assertSame(array('variables'), $bridge->assign_variables('{one}{two}'));
        $this->assertSame(array(array('parameters', 'limit="5"', array('limit' => 10)), array('variables', '{one}{two}')), ee()->services['Variables/Parser']->calls);
    }

    /**
     * Select alert severity and honor only supported optional alert methods.
     *
     * @dataProvider modernBridgeClasses
     * @param string $class
     * @return void
     */
    public function testModernAlerts($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        foreach (array('message_error' => 'asIssue', 'message_failure' => 'asIssue', 'message_notice' => 'asWarning', 'message_success' => 'asSuccess') as $type => $severity) {
            ee()->services['CP/Alert'] = new AlertRecorder();
            $bridge->flashData($type, 'Title', 'Body', array('cannotClose', 'unsupportedMethod'));
            $factoryMethod = substr($class, -3) === 'EE3' ? 'make' : 'makeInline';
            $this->assertSame(array(array($factoryMethod, 'Title'), array($severity), array('withTitle', 'Title'), array('addToBody', 'Body'), array('canClose'), array('cannotClose'), array('defer')), ee()->services['CP/Alert']->calls);
        }
    }

    /**
     * An active alias must keep a human-readable page title.
     *
     * @dataProvider modernBridgeClasses
     * @param string $class
     * @return void
     */
    public function testModernNavigationActiveAlias($class): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->services['CP/URL'] = new Url();
        ee()->services['CP/Sidebar'] = new Sidebar();
        ee()->uri = new Uri();
        ee()->uri->path = 'cp/addons/settings/low_list/detail';
        ee()->view = (object) array('cp_page_title' => 'Before');
        ee()->cp = new ControlPanel();
        $bridge->getNav(array('/' => 'Home', 'license' => 'License', 'https://example.test' => 'Docs'), array('license' => array('save' => 'Save')), array('detail' => 'license'));

        $this->assertSame('License', ee()->view->cp_page_title);
    }

    /**
     * Enumerate every version-specific concrete bridge, including inherited EE6.
     *
     * @return array
     */
    public static function bridgeClasses(): array
    {
        return array_merge(array(array('LowList\\FluxCapacitor\\Bridge\\EE2')), self::modernBridgeClasses());
    }

    /**
     * Enumerate the modern service-container bridge implementations.
     *
     * @return array
     */
    public static function modernBridgeClasses(): array
    {
        return array(array('LowList\\FluxCapacitor\\Bridge\\EE3'), array('LowList\\FluxCapacitor\\Bridge\\EE4'), array('LowList\\FluxCapacitor\\Bridge\\EE5'), array('LowList\\FluxCapacitor\\Bridge\\EE6'));
    }
}
