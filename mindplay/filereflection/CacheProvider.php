<?php

namespace mindplay\filereflection;

/**
 * This interface defines an API to support caching in {@link ReflectionFile}.
 */
interface CacheProvider
{
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
    public function read($key, $timestamp, $refresh);
}
