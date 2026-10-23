<?php

namespace LowListTests;

final class ControlPanelProbe
{
    /**
     * Exercise original CP constructors, menu hooks or optional update providers.
     *
     * @param string $root
     * @param string $case
     * @return array
     * @throws \RuntimeException
     */
    public static function run($root, $case)
    {
        $cases = array('fieldtype-modern', 'fieldtype-legacy', 'tab-modern', 'tab-legacy', 'shared-modern', 'shared-legacy', 'menu-modern', 'menu-legacy', 'updater-present', 'updater-empty', 'updater-missing');
        if (!in_array($case, $cases, true)) {
            throw new \RuntimeException('Unknown control panel context.');
        }
        require_once __DIR__ . '/Environment.php';
        require_once __DIR__ . '/Collaborators.php';
        require_once __DIR__ . '/Url.php';
        require_once __DIR__ . '/Encryption.php';
        $installation = null;
        if (strpos($case, 'updater-') === 0) {
            $installation = sys_get_temp_dir() . '/low-list-updater-' . bin2hex(random_bytes(8));
            mkdir($installation . '/low_list', 0700, true);
        }
        try {
            if ($installation !== null) {
                foreach (array('low_list.phar', 'autoload.php', 'addon.setup.php', 'addon.json') as $file) {
                    symlink($root . '/low_list/' . $file, $installation . '/low_list/' . $file);
                }
                if ($case !== 'updater-missing') {
                    $fixture = $case === 'updater-present' ? 'Extension.php' : 'Empty.php';
                    copy(dirname(__DIR__) . '/Fixtures/control-panel/' . $fixture, $installation . '/low_list/ext.low_list.php');
                }
            }
            define('BASEPATH', __DIR__);
            define('PATH_THIRD', $installation === null ? $root . '/' : $installation . '/');
            define('APP_VER', strpos($case, '-legacy') === false ? '7.5.0' : '2.5.5');
            define('REQ', 'CP');
            define('BASE', 'https://admin.example.test/index.php?');
            define('AMP', '&amp;');
            define('SYSPATH', dirname(__DIR__) . '/Fixtures/');
            define('APPPATH', SYSPATH . 'ee/legacy/');
            $GLOBALS['low_list_ee'] = new Environment();
            ee()->services['CP/URL'] = new UrlFactory();
            $GLOBALS['low_list_encryptions'] = array();
            $GLOBALS['low_list_update_calls'] = array();
            ee()->cp = new ControlPanel();
            ee()->cp->footer[] = '<footer>existing</footer>';
            ee()->db->tables = array('eeharbor_licenses');
            ee()->db->results["SHOW KEYS FROM exp_eeharbor_licenses WHERE Key_name = 'PRIMARY'"] = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
            require $root . '/low_list/autoload.php';
            if (strpos($case, 'updater-') === 0) {
                require dirname(__DIR__) . '/Fixtures/control-panel/Updater.php';
                $updater = new \Low_list_upd();
                ee()->db->calls = array();

                return array(
                    'result' => $updater->update('1.0.0'),
                    'module' => $updater->module_name,
                    'version' => $updater->version,
                    'extension_calls' => $GLOBALS['low_list_update_calls'],
                    'db_calls' => ee()->db->calls,
                );
            }
            if (strpos($case, 'menu-') === 0) {
                $menu = new class {
                    public $calls = array();

                    /**
                     * Observe the hook boundary without pretending to render a native Link.
                     *
                     * @param string $title
                     * @param mixed $url
                     * @return self
                     */
                    public function addItem($title, $url)
                    {
                        $this->calls[] = array($title, $url);

                        return $this;
                    }
                };
                $extension = new \LowList\FluxCapacitor\Base\Ext();
                $returns = array($extension->cpCustomMenu($menu), $extension->cp_custom_menu($menu));

                return array('returns' => $returns, 'items' => array_map(static function ($call) {
                    return array($call[0], is_object($call[1]) ? $call[1]->compile() : $call[1], is_object($call[1]));
                }, $menu->calls));
            }
            $subjects = array();
            if (strpos($case, 'tab-') !== 0) {
                $subjects[] = new class extends \LowList\FluxCapacitor\Base\Ft {
                    /**
                     * Supply the concrete native fieldtype rendering boundary.
                     *
                     * @param mixed $data
                     * @return mixed
                     */
                    public function display_field($data)
                    {
                        return $data;
                    }
                };
            }
            if (strpos($case, 'fieldtype-') !== 0) {
                $subjects[] = new \LowList\FluxCapacitor\Base\Tab();
            }

            return array(
                'versions' => array_map(static function ($subject) { return $subject->version; }, $subjects),
                'bridges' => array_fill(0, count($subjects), get_parent_class('LowList\\FluxCapacitor\\Bridge\\EE')),
                'content_type' => strpos($case, 'tab-') === 0 ? null : $subjects[0]->content_type(),
                'footer' => ee()->cp->footer,
                'footer_alias' => ee()->cp->footer_item === ee()->cp->footer,
                'encryptions' => array_map(static function ($entry) {
                    return array('success' => $entry['success'], 'payload' => json_decode($entry['data'], true));
                }, $GLOBALS['low_list_encryptions']),
            );
        } finally {
            if ($installation !== null) {
                foreach (new \FilesystemIterator($installation . '/low_list') as $file) {
                    unlink($file->getPathname());
                }
                rmdir($installation . '/low_list');
                rmdir($installation);
            }
        }
    }
}
