<?php

namespace LowListTests;

class BridgeAdapterTest extends TestCase
{
    /**
     * Initialize the correct API and preserve EE's XSS result and input shape.
     *
     * @dataProvider bridges
     * @param string $class
     * @return void
     */
    public function testApiAndSecurityDelegation($class): void
    {
        $bridge = $this->bridge($class);
        $api = new LegacyApi();
        ee()->api = $api;
        ee()->legacy_api = $api;
        $this->assertNull($bridge->instantiate('channel_entries'));
        $this->assertSame(array('channel_entries'), $api->calls);
        $security = new Security();
        ee()->security = $security;
        ee()->services['Security/XSS'] = $security;
        $this->assertSame('cleaned-result', $bridge->xss_clean('<b>raw</b>'));
        $security->result = array('html' => 'cleaned');
        $this->assertSame(array('html' => 'cleaned'), $bridge->xss_clean(array('html' => '<b>raw</b>')));
        $this->assertSame(array('<b>raw</b>', array('html' => '<b>raw</b>')), $security->calls);
    }

    /**
     * Replace and remove collaborators using the version's native facade API.
     *
     * @dataProvider bridges
     * @param string $class
     * @return void
     */
    public function testClassReplacementAndRemoval($class): void
    {
        $bridge = $this->bridge($class);
        $replacement = (object) array('marker' => 'replacement');
        if (substr($class, -3) === 'EE2') {
            $bridge->overwriteEEClass('logger', $replacement);
            $this->assertSame($replacement, ee()->logger);
            $bridge->removeEEClass('logger');
            $this->assertSame('', ee()->logger);

            return;
        }
        $bridge->overwriteEEClass('replacement', $replacement);
        $this->assertSame($replacement, ee()->get('replacement'));
        $bridge->removeEEClass('replacement');
        $this->assertFalse(ee()->has('replacement'));
        $bridge->overwriteEEClass('replacement', $replacement);
        $this->assertSame($replacement, ee()->get('replacement'));
    }

    /**
     * Preserve the documented ability to overwrite an already loaded EE object.
     *
     * @dataProvider modernBridges
     * @param string $class
     * @return void
     */
    public function testExistingNativeClassCanBeReplaced($class): void
    {
        $bridge = $this->bridge($class);
        $replacement = (object) array('marker' => 'replacement');

        $bridge->overwriteEEClass('logger', $replacement);

        $this->assertSame($replacement, ee()->get('logger'));
    }

    /**
     * Normalize slug text and call only the format/helper API for that bridge.
     *
     * @dataProvider bridges
     * @param string $class
     * @return void
     */
    public function testTextAndStringHelpers($class): void
    {
        $bridge = $this->bridge($class);
        $formatter = new Formatter();
        ee()->services['Format'] = $formatter;
        $this->assertSame('slug-result', $bridge->url_title('  Mixed CASE  '));
        if (substr($class, -3) === 'EE2' || substr($class, -3) === 'EE3') {
            $this->assertSame(array(array('url_title', 'mixed case')), $GLOBALS['low_list_helpers']);
        } else {
            $this->assertSame(array(array('make', 'Text', 'mixed case'), array('urlSlug'), array('compile')), $formatter->calls);
        }
        if (method_exists($bridge, 'prep_url')) {
            $this->assertSame('prepared-result', $bridge->prep_url('example.test/path'));
        }
        $this->assertSame('reduced-result', $bridge->reduce_double_slashes('https://example.test//path'));
        $this->assertContains(array('reduce_double_slashes', 'https://example.test//path'), $GLOBALS['low_list_helpers']);
        $this->assertContains(array('helper', 'string'), ee()->load->calls);
    }

    /**
     * Query module existence through the proper legacy database or modern model.
     *
     * @dataProvider bridges
     * @param string $class
     * @return void
     */
    public function testModuleExistence($class): void
    {
        $bridge = $this->bridge($class);
        ee()->addons_model = new AddonsModel();
        $this->assertFalse($bridge->module_installed('Example'));
        ee()->db->count = 1;
        ee()->addons_model->installed = true;
        $this->assertTrue($bridge->module_installed('Example'));
        if (substr($class, -3) === 'EE2') {
            $this->assertSame(array(array('where', array('module_name', 'Example')), array('count_all_results', array('modules')), array('where', array('module_name', 'Example')), array('count_all_results', array('modules'))), ee()->db->calls);

            return;
        }
        $this->assertSame(array('Example', 'Example'), ee()->addons_model->calls);
    }

    /**
     * Render modern views with defaults and explicit variables through each bridge.
     *
     * @dataProvider modernBridges
     * @param string $class
     * @return void
     */
    public function testModernViewAndOptionalPagination($class): void
    {
        $bridge = $this->bridge($class);
        ee()->view = (object) array('cp_page_title' => 'Heading');
        ee()->services['CP/URL'] = new Url();
        ee()->services['View'] = new ViewFactory();
        $result = $bridge->view('example', array('custom' => 0), true);
        $this->assertSame('Heading', $result['heading']);
        $this->assertSame('rendered-view', $result['body']);
        $this->assertSame('low_list:example', ee()->services['View']->name);
        $this->assertSame(0, ee()->services['View']->variables['custom']);
        $this->assertSame('7', ee()->services['View']->variables['ee_ver']);
        $result = $bridge->view('unused', array('base_url' => '/explicit', 'cp_page_title' => 'Explicit'), false, 'raw-view');
        $this->assertSame('rendered-view', $result['body']);
        $this->assertSame('raw-view', ee()->services['View']->name);
        $this->assertSame('/explicit', ee()->services['View']->variables['base_url']);
        $this->assertSame('Explicit', ee()->services['View']->variables['cp_page_title']);
        ee()->services['CP/Pagination'] = new Pagination();
        $url = (new Url())->make('empty');
        $this->assertSame('pagination', $bridge->pagination(array('total_rows' => 0, 'per_page' => 10, 'current_page' => 1, 'base_url' => $url)));
        $this->assertSame(array(array('perPage', 10), array('currentPage', 1), array('render', $url)), ee()->services['CP/Pagination']->calls);
    }

    /**
     * Preserve cache values and clear only the selected request-cache namespace.
     *
     * @return void
     */
    public function testLegacyRequestCacheLifecycle(): void
    {
        $bridge = $this->bridge('LowList\\FluxCapacitor\\Bridge\\EE2');
        ee()->session = new Session();
        ee()->cache = new Cache();
        ee()->session->cache['other'] = array('keep' => 'value');
        $this->assertFalse($bridge->cache('unknown'));
        $this->assertFalse($bridge->cache('get', 'missing', false, false));
        $this->assertSame(0, $bridge->cache('set', 'zero', 0, false));
        $this->assertSame(0, $bridge->cache('get', 'zero', false, false));
        $this->assertSame(array('id' => 1), $bridge->cache('set', 'record', array('id' => 1), false));
        $this->assertNull($bridge->cache('delete', 'zero'));
        $this->assertFalse($bridge->cache('get', 'zero', false, false));
        $this->assertSame(array('id' => 1), $bridge->cache('get', 'record', false, false));
        $this->assertNull($bridge->cache('clear'));
        $this->assertArrayNotHasKey('low_list', ee()->session->cache);
        $this->assertSame(array('keep' => 'value'), ee()->session->cache['other']);
        $this->assertTrue($bridge->cache('set', 'disk', 'persistent'));
        $this->assertSame('persistent', $bridge->cache('get', 'disk'));
    }

    /**
     * Deleting a persistent legacy entry must invalidate the stored value.
     *
     * @return void
     */
    public function testLegacyPersistentCacheDeletion(): void
    {
        $bridge = $this->bridge('LowList\\FluxCapacitor\\Bridge\\EE2');
        ee()->session = new Session();
        ee()->cache = new Cache();
        $bridge->cache('set', 'entry', 'stored');
        $bridge->cache('delete', 'entry');

        $this->assertFalse($bridge->cache('get', 'entry'));
    }

    /**
     * Preserve legacy view, footer, pagination, flash and parameter boundaries.
     *
     * @return void
     */
    public function testLegacyViewAndControlPanelServices(): void
    {
        $bridge = $this->bridge('LowList\\FluxCapacitor\\Bridge\\EE2');
        ee()->cp = new ControlPanel();
        ee()->session = new Session();
        ee()->pagination = new LegacyPagination();
        ee()->functions = new VariableParser();
        $this->assertSame('rendered-legacy-view', $bridge->view('example', array('value' => null), true));
        $this->assertSame(array('view', 'example', array('value' => null, 'ee_ver' => '7'), true), end(ee()->load->calls));
        $this->assertSame('legacy-pagination', $bridge->pagination(array('current_page' => 3, 'per_page' => 10)));
        $this->assertSame(array('current_page' => 3, 'per_page' => 10, 'cur_page' => 3), ee()->pagination->options);
        $this->assertSame('legacy-pagination', $bridge->pagination(array('per_page' => 10)));
        $this->assertSame(array('per_page' => 10), ee()->pagination->options);
        $bridge->flashData('message_error', 'Title', 'Body');
        $this->assertSame(array('message_error' => 'Title Body'), ee()->session->flash);
        $bridge->javascript_to_page('legacyScript();');
        $this->assertSame(array('<script type="text/javascript">legacyScript();</script>'), $bridge->getFooter());
        $bridge->getNav(array('https://help.example.test' => 'Help'));
        $this->assertSame(array('Help' => 'https://help.example.test'), ee()->cp->rightNav);
        $bridge->getNav();
        $this->assertSame(array(), ee()->cp->rightNav);
        $this->assertSame('/themes/third_party/low_list/', $bridge->getAddonThemesDir());
        $this->assertSame(APPPATH . 'cache/', $bridge->getCachePath());
        ee()->config->items['cache_path'] = '/configured/cache';
        $this->assertSame('/configured/cache', $bridge->getCachePath());
        $this->assertSame(array('parameters'), $bridge->assign_parameters('limit="5"', array('limit' => 10)));
        $this->assertSame(array('variables'), $bridge->assign_variables('{item}'));
        $this->assertSame(array(array('parameters', 'limit="5"', array('limit' => 10)), array('variables', '{item}')), ee()->functions->calls);
    }

    /**
     * Read complete legacy URL context and restore all request-global state.
     *
     * @return void
     */
    public function testLegacyCurrentUrlContext(): void
    {
        $bridge = $this->bridge('LowList\\FluxCapacitor\\Bridge\\EE2');
        $queryExists = array_key_exists('QUERY_STRING', $_SERVER);
        $query = $queryExists ? $_SERVER['QUERY_STRING'] : null;
        $parameters = $_GET;
        try {
            $_SERVER['QUERY_STRING'] = '/cp/addons/settings/low_list/license/';
            $_GET = array('module' => 'low_list', 'method' => 'license');
            $this->assertSame(array('full' => 'cp/addons/settings/low_list/license', 'cp' => true, 'segments' => array('cp', 'addons', 'settings', 'low_list', 'license'), 'module' => 'low_list', 'method' => 'license'), $bridge->getCurrentUrlInfo());
            $this->assertSame('license', $bridge->getCurrentUrlInfo('method'));
            $this->assertIsArray($bridge->getCurrentUrlInfo('unknown'));
            unset($_GET['method']);
            $this->assertSame('index', $bridge->getCurrentUrlInfo('method'));
        } finally {
            $_GET = $parameters;
            if ($queryExists) {
                $_SERVER['QUERY_STRING'] = $query;
            } else {
                unset($_SERVER['QUERY_STRING']);
            }
        }
    }

    /**
     * Provide every concrete bridge without replacing its shipped implementation.
     *
     * @return array
     */
    public static function bridges(): array
    {
        return array_merge(array(array('LowList\\FluxCapacitor\\Bridge\\EE2')), self::modernBridges());
    }

    /**
     * Provide modern service-container bridges, including inherited EE6.
     *
     * @return array
     */
    public static function modernBridges(): array
    {
        return array(array('LowList\\FluxCapacitor\\Bridge\\EE3'), array('LowList\\FluxCapacitor\\Bridge\\EE4'), array('LowList\\FluxCapacitor\\Bridge\\EE5'), array('LowList\\FluxCapacitor\\Bridge\\EE6'));
    }

    /**
     * Construct one actual archived bridge with normal add-on metadata.
     *
     * @param string $class
     * @return object
     */
    private function bridge($class)
    {
        return new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
    }
}
