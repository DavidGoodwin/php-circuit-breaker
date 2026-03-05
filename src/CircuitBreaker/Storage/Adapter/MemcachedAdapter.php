<?php

namespace DavidGoodwin\CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\StorageException;
use DavidGoodwin\CircuitBreaker\Storage\StorageInterface;

/**
 * Reasonably useful implementation if you needed to share circuit breaker across servers.
 * It incurs the network connection penalty so for optimal performance APC or shared
 *  memory is preferred, but if extra milliseconds are not an issue this
 * adapter could work well. Consider using array adapter to minimise memcache calls.
 *
 * @see StorageInterface
 */
class MemcachedAdapter extends BaseAdapter
{
    private \Memcached $memcached;

    public function __construct(\Memcached $memcached, int $ttl = 3600, ?string $cachePrefix = null)
    {
        parent::__construct($ttl, $cachePrefix);
        $this->memcached = $memcached;
    }

    /**
     * @return void
     */
    protected function checkExtension()
    {
        // nothing to do as you would not have \Memcached instance in constructor if extension was not loaded
    }

    /**
     * Loads item by cache key.
     * @param string $key
     * @return mixed
     */
    protected function load(string $key)
    {
        try {
            $result = $this->memcached->get($key);

            return $result === false ? '' : (string) $result;
        } catch (\Exception $e) {
            throw new StorageException("Failed to load memcached key: $key", 1, $e);
        }
    }

    /**
     * Save item in the cache.
     */
    protected function save(string $key, string $value, int $ttl): void
    {
        try {
            $this->memcached->set($key, $value, $ttl);
        } catch (\Exception $e) {
            throw new StorageException("Failed to save memcached key: $key", 1, $e);
        }
    }
}
