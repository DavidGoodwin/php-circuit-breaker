<?php

namespace DavidGoodwin\CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\Adapter\BaseAdapter;
use DavidGoodwin\CircuitBreaker\Storage\StorageException;
use DavidGoodwin\CircuitBreaker\Storage\StorageInterface;

/**
 * Recommended adapter using APCu local shared memory cache.
 * Super fast, safe, always available (if installed).
 * Does not introduce remote point of failure.
 * Can be efficiently used to load/save each attribute separately if you wish
 *
 * @see StorageInterface
 */
class ApcAdapter extends BaseAdapter
{
    /**
     * Configure instance
     *
     * @param int $ttl          How long should circuit breaker data persist (between updates)
     * @param string  $cachePrefix  Value has to be string. If empty default cache key prefix is used.
     */
    public function __construct(int $ttl = 3600, ?string $cachePrefix = null)
    {
        parent::__construct($ttl, $cachePrefix);
    }

    /**
     * Helper method to make sure that APCu extension is loaded
     *
     * @throws StorageException if APCu is not loaded
     * @return void
     */
    protected function checkExtension()
    {
        if (!function_exists("apcu_store")) {
            throw new StorageException("APCu extension not loaded.");
        }
    }

    /**
     * Loads item by cache key.
     *
     * @param string $key
     * @return mixed
     *
     * @throws StorageException if storage error occurs, handler can not be used
     */
    protected function load(string $key)
    {
        $result = apcu_fetch($key);
        return $result === false ? '' : (string) $result;
    }

    /**
     * Save item in the cache.
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return void
     *
     * @throws StorageException if storage error occurs, handler can not be used
     */
    protected function save(string $key, string $value, int $ttl): void
    {
        $result = apcu_store($key, $value, $ttl);
        if ($result === false) {
            throw new StorageException("Failed to save apc key: $key");
        }
    }
}
