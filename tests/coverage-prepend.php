<?php

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
    $archive = 'phar://' . $root . 'low_list.phar/';
    $coverage = array();
    foreach (xdebug_get_code_coverage() as $path => $data) {
        if (strpos($path, $root) === 0 || strpos($path, $archive) === 0) {
            $coverage[$path] = $data;
        }
    }
    file_put_contents($destination, json_encode(array('php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'), 'coverage' => $coverage), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
});
