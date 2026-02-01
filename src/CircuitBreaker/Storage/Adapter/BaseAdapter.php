<?php

namespace DavidGoodwin\CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\StorageException;
use DavidGoodwin\CircuitBreaker\Storage\StorageInterface;

/**
 * Parent with potentially reusable functions of cache adapters
 *
 * @see StorageInterface
 */
abstract class BaseAdapter implements StorageInterface
{

    /**
     * @var int value in seconds, how long should the stats array persist in cache
     */
    protected int $ttl;

    /**
     * @var string cache key prefix, might be overridden in constructor
     */
    protected string $cachePrefix = "CircuitBreaker";

    /**
     * Configure instance
     *
     * @param int $ttl How long should circuit breaker data persist (between updates)
     * @param string $cachePrefix Value has to be string. If empty default cache key prefix is used.
     */
    public function __construct(int $ttl = 3600, ?string $cachePrefix = null)
    {
        $this->ttl = $ttl;
        if (is_string($cachePrefix)) {
            $this->cachePrefix = $cachePrefix;
        }
    }

    /**
     * Loads circuit breaker service status value.
     * For example failures count or last retry time.
     * Method does not care what are the attribute names. They are not inspected.
     * Any string can be passed as service name and attribute name.
     *
     * @param string $serviceName name of service to load stats for
     * @param string $attributeName name of attribute to load
     * @return    string  value stored or '' if value was not found
     *
     * @throws StorageException if storage error occurs, handler can not be used
     */
    public function loadStatus(string $serviceName, string $attributeName): string
    {
        // make sure extension is loaded
        $this->checkExtension();
        // try to load the data
        $stats = $this->load($this->cachePrefix . $serviceName . $attributeName);
        // if the value loaded is empty return empty string
        if (empty($stats)) {
            $stats = "";
        }
        return $stats;
    }

    /**
     * Saves circuit breaker service status value.
     *
     * Any string can be passed as a service name and attribute name, value can be int/string.
     *
     * Saving in storage is not guaranteed unless flush is set to true.
     * Use calls without a flush if you know you will update more than one value and you want to
     * improve the performance of the calls.
     *
     * @param string $serviceName name of service to load stats for
     * @param string $attributeName name of the attribute to load
     * @param string $value string value loaded or '' if nothing found
     * @param bool $flush set to true will force immediate save, false does not guaranteed saving at all.
     * @return    void
     *
     * @throws StorageException if storage error occurs, handler can not be used
     */
    public function saveStatus(string $serviceName, string $attributeName, string $value, bool $flush = false): void
    {
        // make sure extension is loaded
        $this->checkExtension();
        // store stats
        $this->save($this->cachePrefix . $serviceName . $attributeName, $value, $this->ttl);
    }

    /**
     * Helper method to make sure that extension is loaded (implementation dependent)
     *
     * @return void
     * @throws StorageException if extension is not loaded
     */
    abstract protected function checkExtension();

    /**
     * Loads item by cache key.
     *
     * @param string $key
     * @return mixed
     *
     * @throws StorageException if storage error occurs, handler can not be used
     */
    abstract protected function load(string $key);

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
    abstract protected function save(string $key, string $value, int $ttl): void;
}