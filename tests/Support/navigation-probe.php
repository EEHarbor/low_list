<?php

namespace LowListTests;

final class NavigationProbe
{
    /**
     * Execute the original archive with an optional provider in an owned layout.
     *
     * @param string $root
     * @param string $mode
     * @return array
     * @throws \RuntimeException
     */
    public static function run($root, $mode)
    {
        require_once __DIR__ . '/Environment.php';
        require_once __DIR__ . '/Url.php';
        require_once __DIR__ . '/Collaborators.php';
        $installation = sys_get_temp_dir() . '/low-list-navigation-' . bin2hex(random_bytes(8));
        mkdir($installation . '/low_list/Conduit', 0700, true);
        try {
            foreach (array('low_list.phar', 'addon.setup.php', 'addon.json', 'autoload.php') as $file) {
                symlink($root . '/low_list/' . $file, $installation . '/low_list/' . $file);
            }
            symlink(dirname(__DIR__) . '/Fixtures/navigation/McpNav.php', $installation . '/low_list/Conduit/McpNav.php');
            define('BASEPATH', __DIR__);
            define('PATH_THIRD', $installation . '/');
            define('APP_VER', $mode === 'legacy' ? '2.5.5' : '7.5.0');
            define('REQ', 'CP');
            define('BASE', 'https://admin.example.test/index.php?');
            define('AMP', '&amp;');
            require $root . '/low_list/autoload.php';
            require $installation . '/low_list/Conduit/McpNav.php';
            if ($mode === 'legacy') {
                return self::legacy();
            }
            if ($mode === 'http-route') {
                self::environment('http_logs');
                $nav = new \LowList\Conduit\McpNav();

                return array('url' => self::url($nav->getUrl('http_logs')));
            }
            if ($mode !== 'modern') {
                throw new \RuntimeException('Unknown navigation probe mode.');
            }

            return self::modern();
        } finally {
            foreach ((array) glob($installation . '/low_list/Conduit/*') as $file) {
                unlink($file);
            }
            rmdir($installation . '/low_list/Conduit');
            foreach ((array) glob($installation . '/low_list/*') as $file) {
                unlink($file);
            }
            rmdir($installation . '/low_list');
            rmdir($installation);
        }
    }

    /**
     * Install a fresh observable set of native collaborator boundaries.
     *
     * @param string $lastSegment
     * @return void
     */
    private static function environment($lastSegment)
    {
        $GLOBALS['low_list_ee'] = new Environment();
        ee()->uri = new Uri();
        ee()->uri->path = 'cp/addons/settings/low_list' . ($lastSegment === 'low_list' ? '' : '/' . $lastSegment);
        ee()->view = new \stdClass();
        ee()->cp = new ControlPanel();
        ee()->session = new Session();
        ee()->services['CP/Sidebar'] = new Sidebar();
        ee()->services['CP/URL'] = new UrlFactory(ee()->uri->path);
        \LowList\FluxCapacitor\Conduit\StaticCache::clear();
    }

    /**
     * Construct a provider using only its supported extension hooks.
     *
     * @param string $lastSegment
     * @param array|false|null $items
     * @param array|false|null $map
     * @param array|false|null $buttons
     * @param bool $defer
     * @return \LowList\Conduit\ConfiguredNav
     */
    private static function configured($lastSegment, $items, $map = array(), $buttons = array(), $defer = false)
    {
        self::environment($lastSegment);
        $GLOBALS['low_list_navigation_options'] = array('items' => $items, 'map' => $map, 'buttons' => $buttons, 'defer' => $defer);

        return new \LowList\Conduit\ConfiguredNav();
    }

    /**
     * Observe default, configured, aliased, deferred and toolbar behavior together.
     *
     * @return array
     */
    private static function modern()
    {
        $result = array();
        self::environment('license');
        $controller = new \LowList\FluxCapacitor\Base\Mcp();
        $result['controller'] = array('version' => $controller->version, 'class' => get_class($controller->nav), 'nav' => self::describe($controller->nav));
        $items = array('/' => 'Home', 'history' => 'History', 'HEADING-tools' => 'Tools', 'https://docs.example.test' => 'Docs', 'license' => 'My license');
        $buttons = array('history' => array('create' => 'Create', 'import' => 'Import'), 'index' => array());
        $nav = self::configured('history', $items, array('edit' => 'history'), $buttons);
        $result['direct'] = self::describe($nav);
        $active = $nav->getActiveItem();
        $active->withButton('Changed', '/changed');
        $result['active_identity'] = $active === $nav->nav_items['history'] && $nav->nav_items['history']->button === array('text' => 'Changed', 'url' => '/changed');
        $result['alias'] = self::describe(self::configured('edit', $items, array('edit' => 'history'), $buttons));
        $nav = self::configured('low_list', array('' => 'Empty-key home'));
        $nav->formatItems();
        $result['automatic_index'] = self::describe($nav);
        $result['slash_alias'] = self::describe(self::configured('overview', array('/' => 'Slash home'), array('overview' => '/')));
        $result['empty_alias'] = self::describe(self::configured('overview', array('index' => 'Index'), array('overview' => '')));
        $result['explicit_shortname'] = self::describe(self::configured('low_list', $items, array('low_list' => 'history')));
        $result['unmatched'] = self::describe(self::configured('missing', $items));
        $result['false_defaults'] = self::describe(self::configured('missing', false, false, false));
        $result['null_defaults'] = self::describe(self::configured('missing', null, null, null));
        $result['empty_title'] = self::describe(self::configured('history', array('history' => '')));
        $result['null_title'] = self::describe(self::configured('history', array('history' => null)));
        $result['alias_overrides_direct'] = self::describe(self::configured('history', array('index' => 'Home', 'history' => 'History'), array('history' => 'index')));
        $nav = self::configured('history', $items, array(), array(), true);
        $result['deferred_before'] = array('sidebar' => isset($nav->sidebar), 'events' => $nav->events, 'active' => $nav->getActiveItem());
        $nav->generateNav();
        $result['deferred_after'] = self::describe($nav);
        $nav->postGenerateNav();
        $result['manual_post_events'] = $nav->events;
        $nav->setToolbarIcon();
        $result['toolbar_default'] = self::toolbar();
        $nav->setToolbarIcon('Documentation', 'https://docs.example.test', 'help');
        $result['toolbar_custom'] = self::toolbar();
        $nav->setToolbarIcon('', '/', 'home');
        $result['toolbar_empty_title'] = self::toolbar();
        $result['urls'] = array(self::url($nav->getUrl()), self::url($nav->getUrl('/')), self::url($nav->getUrl('')), self::url($nav->getUrl('https://docs.example.test')));

        return $result;
    }

    /**
     * Verify the legacy branch delegates a fully formatted right-hand menu.
     *
     * @return array
     */
    private static function legacy()
    {
        $nav = self::configured('history', array('/' => 'Home', 'history' => 'History', 'https://docs.example.test' => 'Docs'));

        return array('nav' => ee()->cp->rightNav, 'events' => $nav->events, 'sidebar' => isset($nav->sidebar), 'active' => $nav->getActiveItem());
    }

    /**
     * Capture observable headers, object identity and page metadata.
     *
     * @param \LowList\FluxCapacitor\Conduit\McpNav $nav
     * @return array
     */
    private static function describe($nav)
    {
        $items = array();
        foreach ((array) $nav->nav_items as $key => $header) {
            $button = $header->button;
            if ($button !== null) {
                $button['url'] = self::url($button['url']);
            }
            $items[$key] = array('text' => $header->text, 'url' => self::url($header->url), 'button' => $button, 'active' => $header->class);
        }
        $breadcrumbs = array();
        foreach (ee()->cp->breadcrumbs as $breadcrumb) {
            $breadcrumbs[] = array(self::url($breadcrumb[0]), $breadcrumb[1]);
        }
        $active = array_search($nav->getActiveItem(), (array) $nav->nav_items, true);

        return array('items' => $items, 'active' => $active === false ? null : $active, 'title' => isset(ee()->view->cp_page_title) ? ee()->view->cp_page_title : null, 'breadcrumbs' => $breadcrumbs, 'breadcrumb_map' => isset(ee()->view->cp_breadcrumbs) ? ee()->view->cp_breadcrumbs : array(), 'events' => $nav instanceof \LowList\Conduit\ConfiguredNav ? $nav->events : array());
    }

    /**
     * Capture the current toolbar without changing the URL objects it contains.
     *
     * @return array
     */
    private static function toolbar()
    {
        $toolbar = ee()->view->header;
        foreach ($toolbar['toolbar_items'] as &$item) {
            $item['href'] = self::url($item['href']);
        }

        return $toolbar;
    }

    /**
     * Preserve URL shape while exporting an independently built route.
     *
     * @param mixed $url
     * @return mixed
     */
    private static function url($url)
    {
        return $url instanceof Url ? $url->compile() : $url;
    }
}
