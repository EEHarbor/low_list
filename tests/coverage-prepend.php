<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

require_once __DIR__ . '/Support/Coverage.php';

if (!extension_loaded('xdebug') || !defined('XDEBUG_CC_BRANCH_CHECK')) {
    throw new RuntimeException('Genuine Xdebug branch instrumentation is required.');
}

xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
register_shutdown_function(static function () {
    $destination = getenv('LOW_LIST_COVERAGE_FILE');
    if (!$destination) {
        throw new RuntimeException('A unique coverage destination is required.');
    }
    $root = dirname(__DIR__) . '/low_list/';
    $coverage = array();
    foreach (xdebug_get_code_coverage() as $path => $data) {
        if (\LowListTests\Coverage::belongsToSource($path, $root)) {
            $coverage[$path] = $data;
        }
    }
    $metadata = array(
        'php' => PHP_VERSION,
        'xdebug' => phpversion('xdebug'),
        'context' => array(
            'APP_VER' => defined('APP_VER') ? APP_VER : null,
            'REQ' => defined('REQ') ? REQ : null,
            'native_mode' => getenv('LOW_LIST_NATIVE_MODE') ?: null,
            'native_case' => getenv('LOW_LIST_NATIVE_CASE') ?: null,
        ),
        'runtime_declarations' => \LowListTests\Coverage::declarations($root),
        'coverage' => $coverage,
    );
    file_put_contents($destination, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
});
