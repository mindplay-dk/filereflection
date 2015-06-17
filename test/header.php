<?php

require dirname(__DIR__) . '/vendor/autoload.php';

require __DIR__ . '/test.A.php_';
require __DIR__ . '/test.B.php_';
require __DIR__ . '/test.C.php_';
require __DIR__ . '/test.D.php_';

define('TEST_CACHE_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'cache');

function clean_up() {
    foreach (glob(TEST_CACHE_PATH . DIRECTORY_SEPARATOR . '*.php') as $path) {
        if (@unlink($path) !== true) {
            throw new RuntimeException("TEST ABORTED: unable to remove cache test artifact: {$path}");
        }
    }
}
