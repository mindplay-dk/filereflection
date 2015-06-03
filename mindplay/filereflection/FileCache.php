<?php

namespace mindplay\filereflection;

use RuntimeException;

/**
 * File-based cache provider.
 */
class FileCache implements CacheProvider
{
    /**
     * @var string absolute root path to cache folder
     */
    private $root;

    /**
     * @var int file mode for created cached files
     */
    private $file_mode;

    /**
     * @param string $root      absolute root path to a writable cache folder
     * @param int    $file_mode file mode for created cache files
     */
    public function __construct($root, $file_mode = 0777)
    {
        $this->root = $root;
    }

    /**
     * Read from the cache, if the given cache key is available in the cache,
     * and if the timestamp of the cached value is great than the given
     * timestamp; otherwise, invoke a refresh function and store the
     * returned data in the cache.
     *
     * @param string   $key       cache key
     * @param int      $timestamp timestamp of last modification
     * @param callable $refresh   cache data refresh function
     *
     * @return mixed cached data
     */
    public function read($key, $timestamp, $refresh)
    {
        $path = $this->root . DIRECTORY_SEPARATOR . sha1($key) . '.php';

        if (file_exists($path) && filemtime($path) >= $timestamp) {
            return require $path;
        }

        $data = call_user_func($refresh);

        file_put_contents($path, '<?php return ' . var_export($data, true) . ';');

        return $data;
    }

    /**
     * @param string $path
     * @param mixed $data
     *
     * @throws RuntimeException
     *
     * @return void
     */
    private function write($path, $data)
    {
        $mask = umask(0);

        $file_written = @file_put_contents($path, $data) !== false;

        $mode_set = @chmod($path, $this->file_mode) !== false;

        umask($mask);

        if (false === $file_written) {
            throw new RuntimeException("unable to write cache file: {$path}");
        }

        if (false === $mode_set) {
            throw new RuntimeException("unable to set cache file mode: {$path}");
        }
    }
}
