<?php

use mindplay\filereflection\FileCache;
use mindplay\filereflection\CacheProvider;
use mindplay\filereflection\ReflectionFile;

require __DIR__ . '/header.php';

class MockCache implements CacheProvider
{
    private $cache = array();

    private $time = array();

    /**
     * @var int
     */
    public $hit = 0;

    /**
     * @var int
     */
    public $missed = 0;

    /**
     * @inheritdoc
     */
    public function read($key, $timestamp, callable $refresh)
    {
        if (isset($this->cache[$key]) && $this->time[$key] >= $timestamp) {
            $this->hit += 1;

            return $this->cache[$key];
        }

        $this->missed += 1;

        $data = call_user_func($refresh);

        $this->cache[$key] = $data;
        $this->time[$key] = $timestamp;

        return $data;
    }
}

/**
 * @param string             $scenario
 * @param CacheProvider|null $cache
 */
function run_tests($scenario, $cache)
{
    test(
        "Can reflect on files ($scenario)",
        function () use ($cache) {
            $file_a = new ReflectionFile(__DIR__ . '/test.A.php_', $cache);

            eq($file_a->getNamespaceName(), null, 'file does not declare a namespace');

            $file_b = new ReflectionFile(__DIR__ . '/test.B.php_', $cache);

            eq($file_b->getNamespaceName(), 'Hello', 'file correctly reflects the namespace');

            $file_c = new ReflectionFile(__DIR__ . '/test.C.php_', $cache);

            eq($file_c->getNamespaceName(), 'Hello\World', 'file correctly reflects namespace');

            $count = 0;
            $seen_foo = false;
            $seen_bar = false;

            foreach ($file_c->getClasses() as $class) {
                switch ($class->getName()) {
                    case 'Hello\World\Foo':
                        $seen_foo = true;
                        $count += 1;
                        break;

                    case 'Hello\World\Bar':
                        $seen_bar = true;
                        $count += 1;
                        break;

                    default:
                        throw new RuntimeException("unexpected class: {$class->name}");
                }
            }

            eq($count, 2, 'two classes were found');

            ok($seen_foo, 'class Hello\World\Foo was found');
            ok($seen_bar, 'class Hello\World\Bar was found');
        }
    );

    test(
        "Can resolve type-names ($scenario)",
        function () use ($cache) {
            $file = new ReflectionFile(__DIR__ . '/test.C.php_', $cache);

            eq($file->resolveName('Foo'), '\Hello\World\Foo', 'resolves unqualified local name Foo');
            eq($file->resolveName('Bar'), '\Hello\World\Bar', 'resolves unqualified local name Bar');

            eq($file->resolveName('Bat\Wing'), '\Hello\World\Bat\Wing', 'resolves qualified name Bat\Wing');

            eq($file->resolveName('\Hello\World\Foo'), '\Hello\World\Foo', 'resolves fully-qualified local name Foo');
            eq($file->resolveName('\Hello\World\Bar'), '\Hello\World\Bar', 'resolves fully-qualified local name Bar');

            eq($file->resolveName('Baz'), '\Other\World\Baz', 'resolves unqualified local name Baz via use-clause');
            eq($file->resolveName('Fud'), '\Other\World\Nib',
                'resolves unqualified local name-alias Fud via use-clause');

            eq($file->resolveName('\Other\World\Baz'), '\Other\World\Baz',
                'resolves fully-qualified name Other\World\Baz');
            eq($file->resolveName('\Other\World\Nib'), '\Other\World\Nib',
                'resolves fully-qualified name Other\World\Nib');
            eq($file->resolveName('\Other\World\Fud'), '\Other\World\Fud',
                'resolves fully-qualified name Other\World\Fud');

            eq($file->getClass('Baz')->getName(), 'Other\World\Baz',
                'resolves unqualified local name when calling getClass()');
            eq($file->getClass('Fud')->getName(), 'Other\World\Nib',
                'resolves unqualified local name when calling getClass()');

            eq($file->resolveName('string'), 'string', 'resolves psuedo-type name string (without modification)');
        }
    );
}

clean_up();

run_tests("uncached", null);

$mock_cache = new MockCache();

run_tests("with cold mock cache", $mock_cache);

eq($mock_cache->hit, 1);
eq($mock_cache->missed, 3);

$mock_cache->hit = 0;
$mock_cache->missed = 0;

run_tests("again with warm mock cache", $mock_cache);

eq($mock_cache->hit, 4);
eq($mock_cache->missed, 0);

$file_cache = new FileCache(TEST_CACHE_PATH);

run_tests("with cold file cache", $file_cache);
run_tests("again with warm file cache", $file_cache);

clean_up();

test(
    'throws for invalid argument to getClass()',
    function () {
        $file = new ReflectionFile(__DIR__ . '/test.B.php_');

        expect(
            'InvalidArgumentException',
            'should throw if passing a simple pseudo-type name',
            function () use ($file) {
                $class = $file->getClass('string');
            }
        );

        expect(
            'InvalidArgumentException',
            'should throw if passing an undefined class-name',
            function () use ($file) {
                $class = $file->getClass('Blah');
            }
        );
    }
);

exit(status());

// https://gist.github.com/mindplay-dk/4260582

/**
 * @param string   $name     test description
 * @param callable $function test implementation
 */
function test($name, $function)
{
    echo "\n=== $name ===\n\n";

    try {
        call_user_func($function);
    } catch (Exception $e) {
        ok(false, "UNEXPECTED EXCEPTION", $e);
    }
}

/**
 * @param bool   $result result of assertion
 * @param string $why    description of assertion
 * @param mixed  $value  optional value (displays on failure)
 */
function ok($result, $why = null, $value = null)
{
    if ($result === true) {
        echo "- PASS: " . ($why === null ? 'OK' : $why) . ($value === null ? '' : ' (' . format($value) . ')') . "\n";
    } else {
        echo "# FAIL: " . ($why === null ? 'ERROR' : $why) . ($value === null ? '' : ' - ' . format($value, true)) . "\n";
        status(false);
    }
}

/**
 * @param mixed  $value    value
 * @param mixed  $expected expected value
 * @param string $why      description of assertion
 */
function eq($value, $expected, $why = null)
{
    $result = $value === $expected;

    $info = $result
        ? format($value)
        : "expected: " . format($expected, true) . ", got: " . format($value, true);

    ok($result, ($why === null ? $info : "$why ($info)"));
}

/**
 * @param string   $exception_type Exception type name
 * @param string   $why            description of assertion
 * @param callable $function       function expected to throw
 */
function expect($exception_type, $why, $function)
{
    try {
        call_user_func($function);
    } catch (Exception $e) {
        if ($e instanceof $exception_type) {
            ok(true, $why, $e);
            return;
        } else {
            $actual_type = get_class($e);
            ok(false, "$why (expected $exception_type but $actual_type was thrown)");
            return;
        }
    }

    ok(false, "$why (expected exception $exception_type was NOT thrown)");
}

/**
 * @param mixed $value
 * @param bool  $verbose
 *
 * @return string
 */
function format($value, $verbose = false)
{
    if ($value instanceof Exception) {
        return get_class($value) . ": \"" . $value->getMessage() . "\"";
    }

    if (! $verbose && is_array($value)) {
        return 'array[' . count($value) . ']';
    }

    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }

    return print_r($value, true);
}

/**
 * @param bool|null $status test status
 *
 * @return int number of failures
 */
function status($status = null)
{
    static $failures = 0;

    if ($status === false) {
        $failures += 1;
    }

    return $failures;
}
