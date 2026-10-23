<?php

namespace LowListTests;

final class ControllerLoadingProbe
{
    /**
     * Discover optional providers through the original controller and retained classes.
     *
     * @param string $root
     * @param string $case
     * @return array
     * @throws \RuntimeException
     */
    public static function run($root, $case)
    {
        $cases = array('modern-present', 'legacy-present', 'modern-empty', 'legacy-empty', 'modern-missing', 'legacy-missing', 'modern-predeclared', 'legacy-predeclared');
        if (!in_array($case, $cases, true)) {
            throw new \RuntimeException('Unknown controller loading case.');
        }
        require_once __DIR__ . '/Environment.php';
        require_once __DIR__ . '/Url.php';
        require_once __DIR__ . '/Collaborators.php';
        $installation = sys_get_temp_dir() . '/low-list-controller-loading-' . bin2hex(random_bytes(8));
        mkdir($installation . '/low_list/Conduit', 0700, true);
        try {
            foreach (array('low_list.phar', 'addon.setup.php', 'addon.json', 'autoload.php') as $file) {
                symlink($root . '/low_list/' . $file, $installation . '/low_list/' . $file);
            }
            $provider = $installation . '/low_list/Conduit/McpNav.php';
            $predeclared = substr($case, -11) === 'predeclared';
            if (substr($case, -7) === 'present' || $predeclared) {
                symlink(dirname(__DIR__) . '/Fixtures/navigation/McpNav.php', $provider);
            }
            if (substr($case, -5) === 'empty') {
                file_put_contents($provider, "<?php\n");
            }
            define('BASEPATH', __DIR__);
            define('PATH_THIRD', $installation . '/');
            define('APP_VER', strpos($case, 'legacy-') === 0 ? '2.5.5' : '7.5.0');
            define('REQ', 'CP');
            define('BASE', 'https://admin.example.test/index.php?');
            define('AMP', '&amp;');
            $GLOBALS['low_list_ee'] = new Environment();
            ee()->uri = new Uri();
            ee()->uri->path = 'cp/addons/settings/low_list';
            ee()->view = new \stdClass();
            ee()->cp = new ControlPanel();
            ee()->session = new Session();
            ee()->services['CP/Sidebar'] = new Sidebar();
            ee()->services['CP/URL'] = new UrlFactory(ee()->uri->path);
            require $root . '/low_list/autoload.php';
            if ($predeclared) {
                require $provider;
                unlink($provider);
            }
            $before = class_exists('LowList\\Conduit\\McpNav', false);
            $controller = new \LowList\FluxCapacitor\Base\Mcp();

            return array(
                'loaded_before' => $before,
                'loaded_after' => class_exists('LowList\\Conduit\\McpNav', false),
                'version' => $controller->version,
                'nav_class' => $controller->nav === null ? null : get_class($controller->nav),
                'nav_parent' => $controller->nav === null ? null : get_parent_class($controller->nav),
                'sidebar' => $controller->nav !== null && isset($controller->nav->sidebar),
                'right_nav' => ee()->cp->rightNav,
                'provider_hash' => is_file($provider) ? hash_file('sha256', $provider) : null,
            );
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
}
