<?php

use mindplay\benchpress\Benchmark;
use mindplay\filereflection\FileCache;
use mindplay\filereflection\ReflectionFile;

require __DIR__ . '/header.php';

$bench = new Benchmark();

$bench->add(
    'uncached initialization overhead',
    function () {
        $file = new ReflectionFile(__DIR__ . '/test.C.php_');
    }
);

$cache = new FileCache(__DIR__ . '/build/cache');

$bench->add(
    'cached initialization overhead',
    function () use ($cache) {
        $file = new ReflectionFile(__DIR__ . '/test.C.php_', $cache);
    }
);

clean_up();

$bench->run();

clean_up();
