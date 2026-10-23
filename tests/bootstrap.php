<?php

require_once __DIR__ . '/Support/Environment.php';
require_once __DIR__ . '/Support/Url.php';
require_once __DIR__ . '/Support/Collaborators.php';

define('BASEPATH', __DIR__);
define('PATH_THIRD', dirname(__DIR__) . '/');
define('APP_VER', '7.5.0');
define('REQ', 'PAGE');
define('BASE', 'https://admin.example.test/index.php?');
define('AMP', '&amp;');
define('SYSPATH', __DIR__ . '/Fixtures/');
define('APPPATH', __DIR__ . '/Fixtures/ee/legacy/');
$cachePath = tempnam(sys_get_temp_dir(), 'low-list-tests-');
unlink($cachePath);
define('PATH_CACHE', $cachePath . '/');
define('DIR_WRITE_MODE', 0700);
define('FILE_WRITE_MODE', 0600);

require_once PATH_THIRD . 'low_list/autoload.php';
require_once PATH_THIRD . 'low_list/pi.low_list.php';
require_once __DIR__ . '/Support/TestCase.php';
