<?php

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

if ($argv[1] === 'diagnostic') {
    @trigger_error('suppressed diagnostic probe', E_USER_DEPRECATED);
    exit(1);
}

$root = dirname(__DIR__, 2);
if ($argv[1] === 'direct') {
    require $root . '/low_list/pi.low_list.php';
    exit(1);
}
require __DIR__ . '/Environment.php';
$GLOBALS['low_list_ee'] = new \LowListTests\Environment();
define('BASEPATH', __DIR__);
define('PATH_THIRD', $root . '/');
define('APP_VER', isset($argv[3]) ? $argv[3] : '7.5.0');
define('REQ', 'PAGE');
define('SYSPATH', dirname(__DIR__) . '/Fixtures/');
define('APPPATH', SYSPATH . 'ee/legacy/');
define('PATH_CACHE', sys_get_temp_dir() . '/low-list-native-' . getmypid() . '/');
require $root . '/low_list/autoload.php';

$target = $argv[2];
if ($argv[1] === 'fallback') {
    stream_wrapper_unregister('phar');
}
if ($argv[1] === 'archive' || $argv[1] === 'bridge') {
    require 'phar://' . $root . '/low_list/low_list.phar/' . $target;
} else {
    require $root . '/' . $target;
}
if ($argv[1] === 'bridge') {
    $flux = new \LowList\FluxCapacitor\FluxCapacitor();
    $major = substr(APP_VER, 0, 1);
    $expected = '\\LowList\\FluxCapacitor\\Bridge\\EE' . (in_array($major, array('2', '3', '4', '5'), true) ? $major : '6');
    if (!is_a($flux, $expected) || $flux->getEEVersion() !== $major) {
        throw new RuntimeException('Wrong native EE bridge selected.');
    }
}
echo 'OK';
