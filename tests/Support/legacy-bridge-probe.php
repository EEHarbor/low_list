<?php

namespace LowListTests;

final class LegacyBridgeProbe
{
    /**
     * Run the original EE2 bridge in a disposable installation and request.
     *
     * @param string $root
     * @param string $mode
     * @return array
     * @throws \RuntimeException
     */
    public static function run($root, $mode)
    {
        require_once __DIR__ . '/Environment.php';
        require_once __DIR__ . '/Collaborators.php';
        $installation = sys_get_temp_dir() . '/low-list-legacy-' . bin2hex(random_bytes(8));
        mkdir($installation . '/low_list/views', 0700, true);
        try {
            foreach (array('low_list.phar', 'addon.setup.php', 'addon.json', 'autoload.php') as $file) {
                symlink($root . '/low_list/' . $file, $installation . '/low_list/' . $file);
            }
            define('BASEPATH', __DIR__);
            define('PATH_THIRD', $installation . '/');
            $versions = array('slashes' => '2.5.4', 'slashes-helper' => '2.10.1');
            define('APP_VER', isset($versions[$mode]) ? $versions[$mode] : '2.5.5');
            define('REQ', 'CP');
            define('BASE', 'https://admin.example.test/index.php?');
            define('AMP', '&amp;');
            require $root . '/low_list/autoload.php';
            if ($mode === 'slashes' || $mode === 'slashes-helper') {
                return self::slashes();
            }
            if ($mode === 'urls') {
                return self::urls();
            }
            if ($mode === 'view-writable' || $mode === 'view-readonly') {
                return self::views($installation . '/low_list/views', $mode === 'view-readonly');
            }

            throw new \RuntimeException('Unknown legacy bridge mode.');
        } finally {
            chmod($installation . '/low_list/views', 0700);
            foreach ((array) glob($installation . '/low_list/views/*') as $file) {
                unlink($file);
            }
            rmdir($installation . '/low_list/views');
            foreach ((array) glob($installation . '/low_list/*') as $file) {
                unlink($file);
            }
            rmdir($installation . '/low_list');
            rmdir($installation);
        }
    }

    /**
     * Create the native session/config boundaries for one independent URL call.
     *
     * @param mixed $sessionType
     * @param array $values
     * @return \LowList\FluxCapacitor\Bridge\EE2
     */
    private static function bridge($sessionType = 'c', array $values = array())
    {
        $GLOBALS['low_list_ee'] = new Environment();
        ee()->session = new Session();
        ee()->session->values = $values;
        ee()->config->items['cp_session_type'] = $sessionType;

        return new \LowList\FluxCapacitor\Bridge\EE2(array('module' => 'low_list', 'module_name' => 'Low List'));
    }

    /**
     * Run the original EE2 bridge against its verified version-specific slash API.
     *
     * @return array
     */
    private static function slashes()
    {
        $bridge = self::bridge();
        ee()->functions = new Functions();
        $GLOBALS['low_list_helpers'] = array();
        $values = array('', '0', '/', '//', '///', 'path//to///file',
            'https://example.test//path///', 'http://example.test',
            '//example.test///path', 'scheme:////path', "binary\0//path",
            'https://example.test/?next=http://other.test//path');
        $result = array('version' => APP_VER, 'values' => array());
        foreach ($values as $value) {
            $result['values'][] = $bridge->reduce_double_slashes($value);
        }
        $result['loader_calls'] = ee()->load->calls;
        $result['helper_calls'] = $GLOBALS['low_list_helpers'];

        return $result;
    }

    /**
     * Observe session selection and existing query parameters without private state.
     *
     * @return array
     */
    private static function urls()
    {
        $cases = array(
            'default' => array('c', array(), '', ''),
            'slash' => array('c', array(), '/', ''),
            'method-extra' => array('c', array(), 'license', AMP . 'filter=active'),
            'session-missing' => array('s', array(), '', ''),
            'session-id' => array('s', array('session_id' => 'session'), '', ''),
            'session-zero' => array('s', array('session_id' => 0), '', ''),
            'session-string-zero' => array('s', array('session_id' => '0'), '', ''),
            'session-null' => array('s', array('session_id' => null), '', ''),
            'fingerprint' => array('cs', array('fingerprint' => 'fingerprint'), '', ''),
            'fingerprint-empty' => array('cs', array('fingerprint' => ''), '', ''),
            'cookie-only' => array('c', array('session_id' => 'session', 'fingerprint' => 'fingerprint'), '', ''),
            'unset-type' => array(null, array('session_id' => 'session'), '', ''),
            'existing' => array('s', array('session_id' => 'session'), '', AMP . 'S=existing' . AMP . 'D=cp'),
            'empty-existing' => array('s', array('session_id' => 'session'), '', AMP . 'S=' . AMP . 'D='),
            'zero-existing' => array('s', array('session_id' => 'session'), '', AMP . 'S=0' . AMP . 'D=0'),
            'directive-only' => array('s', array('session_id' => 'session'), '', AMP . 'D=cp'),
            'session-only' => array('s', array('session_id' => 'session'), '', AMP . 'S=existing'),
        );
        $result = array('base_urls' => array(), 'module_urls' => array());
        foreach ($cases as $name => $case) {
            $bridge = self::bridge($case[0], $case[1]);
            $result['base_urls'][$name] = $bridge->getBaseURL($case[2], $case[3]);
        }
        $bridge = self::bridge();
        $result['module_urls']['default'] = $bridge->moduleURL();
        $result['module_urls']['null'] = $bridge->moduleURL('license', null);
        $result['module_urls']['empty'] = $bridge->moduleURL('license', array());
        $result['module_urls']['multiple'] = $bridge->moduleURL('license', array('id' => 3, 'zero' => 0, 'empty' => '', 'nullable' => null, 'flag' => false));

        return $result;
    }

    /**
     * Observe owned inline writes, falsey strings and loader output semantics.
     *
     * @param string $directory
     * @param bool $readonly
     * @return array
     * @throws \RuntimeException
     */
    private static function views($directory, $readonly)
    {
        $bridge = self::bridge();
        $payload = '<?php echo "owned-inline-view"; ?>';
        $templates = array('example', 'unused', 'zero', 'empty');
        foreach ($templates as $name) {
            file_put_contents($directory . '/' . $name . '.php', 'owned-original-' . $name);
        }
        if ($readonly) {
            file_put_contents($directory . '/dynamic.php', 'preserved-view');
            chmod($directory, 0500);
        }
        $writable = is_writable($directory);
        if ($writable === $readonly) {
            throw new \RuntimeException('The owned view directory has the wrong write permission.');
        }
        $result = array('writable' => $writable, 'returns' => array());
        $result['returns'][] = $bridge->view('example', array('value' => null), true);
        $result['returns'][] = $bridge->view('unused', array('value' => 0, 'ee_ver' => 'caller'), true, $payload);
        $result['returns'][] = $bridge->view('unused', array(), false, $payload);
        $result['returns'][] = $bridge->view('zero', array(), true, '0');
        $result['returns'][] = $bridge->view('empty', array(), true, '');
        $result['written'] = file_get_contents($directory . '/dynamic.php');
        foreach ($templates as $name) {
            $result['templates'][$name] = file_get_contents($directory . '/' . $name . '.php');
        }
        $result['calls'] = ee()->load->calls;
        $result['output'] = ee()->load->viewOutput;

        return $result;
    }
}
