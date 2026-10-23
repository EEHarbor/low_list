<?php

namespace LowListTests;

final class LoadingProbe
{
    /**
     * Load original procedural source before bootstrap can predeclare its globals.
     *
     * @param string $root
     * @param string $mode
     * @param string $case
     * @return array
     * @throws \RuntimeException
     */
    public static function run($root, $mode, $case)
    {
        $cases = array(
            'bootstrap' => array('configured', 'defined-legacy', 'defined-modern'),
            'fieldtype-loading' => array('system', 'app', 'priority', 'installer', 'installer-ancestor', 'predeclared', 'defined-version'),
        );
        if (!isset($cases[$mode]) || !in_array($case, $cases[$mode], true)) {
            throw new \RuntimeException('Unknown native loading context.');
        }
        define('BASEPATH', __DIR__);
        define('PATH_THIRD', $root . '/');
        define('REQ', 'PAGE');
        if ($mode === 'bootstrap') {
            return self::bootstrap($root, $case);
        }

        return self::fieldtype($root, $case);
    }

    /**
     * Exercise the original fallback function and configured/predefined version order.
     *
     * @param string $root
     * @param string $case
     * @return array
     */
    private static function bootstrap($root, $case)
    {
        $config = new class {
            public $calls = array();

            /**
             * Supply the native config item requested by the source selector.
             *
             * @param string $name
             * @return string
             * @throws \LogicException
             */
            public function item($name)
            {
                if ($name !== 'app_version') {
                    throw new \LogicException('Unexpected native bootstrap config item.');
                }
                $this->calls[] = $name;

                return '2.5.5';
            }
        };
        $GLOBALS['low_list_instance'] = (object) array('config' => $config);
        $GLOBALS['low_list_instance_calls'] = 0;
        require dirname(__DIR__) . '/Fixtures/loading/instance.php';
        if ($case !== 'configured') {
            define('APP_VER', $case === 'defined-legacy' ? '2.5.5' : '7.5.0');
        }
        require $root . '/low_list/autoload.php';
        $bridge = new \LowList\FluxCapacitor\Bridge\EE(array('module' => 'low_list', 'module_name' => 'Low List'));

        return array(
            'identity' => ee() === $GLOBALS['low_list_instance'] && ee() === $GLOBALS['low_list_instance'],
            'version' => APP_VER,
            'parent' => get_parent_class($bridge),
            'config_calls' => $config->calls,
            'instance_calls' => $GLOBALS['low_list_instance_calls'],
            'source' => (new \ReflectionFunction('ee'))->getFileName(),
        );
    }

    /**
     * Select bounded native parent files from owned layouts without changing source.
     *
     * @param string $root
     * @param string $case
     * @return array
     */
    private static function fieldtype($root, $case)
    {
        require_once __DIR__ . '/Environment.php';
        $owned = sys_get_temp_dir() . '/low-list-loading-' . bin2hex(random_bytes(8));
        mkdir($owned, 0700);
        $owned = realpath($owned);
        $base = $owned . ($case === 'installer-ancestor' ? '/installer-backup' : '');
        $system = $base . '/system/';
        $app = $base . (in_array($case, array('installer', 'installer-ancestor'), true) ? '/installer/' : '/application/');
        $fixture = dirname(__DIR__) . '/Fixtures/ee/legacy/fieldtypes/EE_Fieldtype.php';
        try {
            $files = array();
            if (in_array($case, array('system', 'priority', 'defined-version'), true)) {
                $files[] = $system . 'ee/legacy/fieldtypes/EE_Fieldtype.php';
            }
            if ($case === 'app' || $case === 'priority') {
                $files[] = $app . 'fieldtypes/EE_Fieldtype.php';
            }
            if ($case === 'installer' || $case === 'installer-ancestor') {
                $files[] = $base . '/expressionengine/fieldtypes/EE_Fieldtype.php';
            }
            foreach ($files as $file) {
                mkdir(dirname($file), 0700, true);
                copy($fixture, $file);
            }
            define('APP_VER', '7.5.0');
            define('SYSPATH', $system);
            define('APPPATH', $app);
            if ($case === 'predeclared') {
                require $fixture;
            }
            if ($case === 'defined-version') {
                define('LowList\\VERSION', 'predefined-test-version');
            }
            $GLOBALS['low_list_ee'] = new Environment();
            require $root . '/low_list/autoload.php';
            $field = new class extends \LowList\FluxCapacitor\Base\Ft {
                /**
                 * Supply the native parent's required concrete field rendering method.
                 *
                 * @param mixed $data
                 * @return mixed
                 */
                public function display_field($data)
                {
                    return $data;
                }
            };
            $parent = (new \ReflectionClass('EE_Fieldtype'))->getFileName();

            return array(
                'parent' => $parent,
                'parent_relative' => $parent === $fixture ? '@fixture' : substr($parent, strlen($owned) + 1),
                'parent_hash' => hash_file('sha256', $parent),
                'version' => $field->version,
                'info_version' => $field->info['version'],
                'content_id' => $field->content_id(),
            );
        } finally {
            self::removeDirectory($owned);
        }
    }

    /**
     * Remove only owned layout contents without following a symbolic link.
     *
     * @param string $path
     * @return void
     */
    private static function removeDirectory($path)
    {
        if (is_link($path) || !is_dir($path)) {
            unlink($path);

            return;
        }
        foreach (new \FilesystemIterator($path) as $file) {
            self::removeDirectory($file->getPathname());
        }
        rmdir($path);
    }
}
