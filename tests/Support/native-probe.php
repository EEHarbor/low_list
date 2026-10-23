<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);
ini_set('display_errors', 'stderr');

if ($argv[1] === 'diagnostic') {
    @trigger_error('suppressed diagnostic probe', E_USER_DEPRECATED);
    exit(1);
}
if ($argv[1] === 'stall') {
    echo "STALL_READY\n";
    fwrite(STDERR, "STALL_STDERR\n");
    while (true) {
        usleep(100000);
    }
}
if ($argv[1] === 'validation-abnormal-exit') {
    echo 'Invalid internal .phar file (checksum error)';
    exit(23);
}

$root = dirname(__DIR__, 2);
if ($argv[1] === 'control-panel') {
    require __DIR__ . '/control-panel-probe.php';
    echo json_encode(\LowListTests\ControlPanelProbe::run($root, $argv[2]), JSON_UNESCAPED_SLASHES);
    exit(0);
}
if ($argv[1] === 'fieldtype') {
    require __DIR__ . '/fieldtype-probe.php';
    echo json_encode(\LowListTests\FieldtypeProbe::run($root, $argv[2]), JSON_UNESCAPED_SLASHES);
    exit(0);
}
if ($argv[1] === 'legacy-bridge') {
    require __DIR__ . '/legacy-bridge-probe.php';
    echo json_encode(\LowListTests\LegacyBridgeProbe::run($root, $argv[2]), JSON_UNESCAPED_SLASHES);
    exit(0);
}
if ($argv[1] === 'navigation') {
    require __DIR__ . '/navigation-probe.php';
    echo json_encode(\LowListTests\NavigationProbe::run($root, $argv[2]), JSON_UNESCAPED_SLASHES);
    exit(0);
}
if ($argv[1] === 'direct') {
    require $root . '/low_list/pi.low_list.php';
    exit(1);
}
require __DIR__ . '/Environment.php';
$GLOBALS['low_list_ee'] = new \LowListTests\Environment();
define('BASEPATH', __DIR__);
define('PATH_THIRD', $argv[1] === 'loader-missing-archive' ? sys_get_temp_dir() . '/absent-install/' : $root . '/');
define('APP_VER', isset($argv[3]) ? $argv[3] : '7.5.0');
define('REQ', 'PAGE');
define('SYSPATH', dirname(__DIR__) . '/Fixtures/');
define('APPPATH', SYSPATH . 'ee/legacy/');
define('PATH_CACHE', sys_get_temp_dir() . '/low-list-native-' . getmypid() . '/');
require $root . '/low_list/autoload.php';

$target = $argv[2];
if (strpos($argv[1], 'loader-') === 0) {
    if ($argv[1] === 'loader-opcache') {
        if (function_exists('opcache_get_configuration')) {
            $configuration = opcache_get_configuration();
            if ($configuration['directives']['opcache.validate_permission'] !== true) {
                throw new RuntimeException('The child opcache configuration was not inherited.');
            }
        }
        if (!class_exists('LowList\\FluxCapacitor\\Conduit\\StaticCache')) {
            throw new RuntimeException('The original loader did not load an existing class.');
        }
        echo 'OK';
        exit(0);
    }
    $expected = $argv[1] === 'loader-missing-archive'
        ? 'Add-on PHAR file at location ' . PATH_THIRD . 'low_list/low_list.phar not found.'
        : 'Class at location phar://' . PATH_THIRD . 'low_list/low_list.phar/FluxCapacitor/Missing.php not found.';
    try {
        class_exists('LowList\\FluxCapacitor\\Missing');
        throw new RuntimeException('The missing loader resource was not rejected.');
    } catch (Exception $exception) {
        if ($exception->getMessage() !== $expected) {
            throw $exception;
        }
    }
    echo 'OK';
    exit(0);
}
if ($argv[1] === 'stub-manifest') {
    require $root . '/low_list/low_list.phar';
    $source = fopen($root . '/low_list/low_list.phar', 'rb');
    fseek($source, Extract_low_list::LEN);
    $length = unpack('V', fread($source, 4));
    $manifest = fread($source, $length[1]);
    fclose($source);
    try {
        Extract_low_list::_unpack($target === 'empty' ? '' : substr($manifest, 0, 18));
    } catch (ErrorException $exception) {
        throw $exception;
    } catch (Exception $exception) {
        if (stripos($exception->getMessage(), 'manifest') === false) {
            throw $exception;
        }
        echo 'REJECTED';
        exit(0);
    }
    echo 'UNEXPECTED_RETURN';
    exit(1);
}
if ($argv[1] === 'stub-extract') {
    require $root . '/low_list/low_list.phar';
    $contents = 'payload';
    $encoded = $contents;
    $flags = 0;
    $size = strlen($contents);
    $checksum = sprintf('%u', crc32($contents));
    if ($target === 'size') {
        $size++;
    }
    if ($target === 'checksum') {
        $checksum = sprintf('%u', crc32($contents) ^ 1);
    }
    if ($target === 'gzip') {
        $flags = Extract_low_list::GZ;
        $encoded = 'invalid-gzip-payload';
    }
    if ($target === 'bzip2') {
        $flags = Extract_low_list::BZ2;
        $encoded = 'invalid-bzip2-payload';
    }
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $encoded);
    rewind($stream);
    Extract_low_list::extractFile('fixture.bin', array($size, 0, strlen($encoded), $checksum, $flags), $stream);
    echo 'UNEXPECTED_RETURN';
    exit(1);
}
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
