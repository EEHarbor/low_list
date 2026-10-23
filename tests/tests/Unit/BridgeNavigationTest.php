<?php

namespace LowListTests;

class BridgeNavigationTest extends TestCase
{
    /**
     * Supply direct, root, unmatched and empty requests for every modern bridge.
     *
     * @return array
     */
    public static function requests(): array
    {
        $requests = array(
            'root' => array('low_list', 0, 1, false),
            'index' => array('low_list/index', 0, 2, false),
            'history' => array('low_list/history', 1, 2, false, array(), array('history' => array('create' => 'Create', 'import' => 'Import'))),
            'unmatched' => array('low_list/missing', null, 0, false),
            'empty' => array('low_list/missing', null, 0, true),
            'missing alias' => array('low_list/detail', null, 0, false, array('detail' => 'disabled')),
        );
        $cases = array();
        foreach (array('EE3', 'EE4', 'EE5', 'EE6') as $bridge) {
            foreach ($requests as $name => $request) {
                $cases[$bridge . ' ' . $name] = array_merge(array('LowList\\FluxCapacitor\\Bridge\\' . $bridge), $request);
            }
        }

        return $cases;
    }

    /**
     * Activate the direct header and preserve existing metadata for unmatched routes.
     *
     * @dataProvider requests
     * @param string $class
     * @param string $path
     * @param int|null $selected
     * @param int $activeCalls
     * @param bool $empty
     * @param array $activeMap
     * @param array $buttons
     * @return void
     */
    public function testDirectNavigationState($class, $path, $selected, $activeCalls, $empty, $activeMap = array(), $buttons = array()): void
    {
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));
        ee()->uri = new Uri();
        ee()->uri->path = 'cp/addons/settings/' . $path;
        ee()->view = (object) array('cp_page_title' => 'Before');
        ee()->cp = new ControlPanel();
        $sidebar = new Sidebar();
        ee()->services['CP/Sidebar'] = $sidebar;
        ee()->services['CP/URL'] = new UrlFactory(ee()->uri->path);
        $items = $empty ? array() : array('/' => 'Home', 'history' => 'History', 'https://docs.example.test' => 'Docs');

        $this->assertNull($bridge->getNav($items, $buttons, $activeMap));

        $expected = $empty ? array() : array(
            array('Home', '/cp/addons/settings/low_list/index', '', null),
            array('History', '/cp/addons/settings/low_list/history', '', null),
            array('Docs', 'https://docs.example.test', '', null),
        );
        if ($selected !== null) {
            $expected[$selected][2] = str_repeat('active ', $activeCalls);
        }
        if ($buttons) {
            $expected[1][3] = array('text' => 'Import', 'url' => '/cp/addons/settings/low_list/import');
        }
        $actual = array();
        foreach ($sidebar->headers as $header) {
            $button = $header->button;
            if ($button !== null) {
                $button['url'] = $button['url'] instanceof Url ? $button['url']->compile() : $button['url'];
            }
            $actual[] = array($header->text, $header->url instanceof Url ? $header->url->compile() : $header->url, $header->class, $button);
        }
        $this->assertSame($expected, $actual);
        $title = $selected === null ? 'Before' : $expected[$selected][0];
        $this->assertSame($title, ee()->view->cp_page_title);
        $this->assertCount($selected === null ? 0 : 1, ee()->cp->breadcrumbs);
        if ($selected !== null) {
            $breadcrumb = ee()->cp->breadcrumbs[0];
            $this->assertInstanceOf(Url::class, $breadcrumb[0]);
            $this->assertSame('/cp/addons/settings/low_list/index', $breadcrumb[0]->compile());
            $this->assertSame('LowList', $breadcrumb[1]);
            $this->assertNotSame($sidebar->headers[0]->url, $breadcrumb[0]);
            $this->assertSame(array('/cp/addons/settings/low_list/index' => 'LowList'), ee()->view->cp_breadcrumbs);
        }
    }
}
