<?php

/**
 * This file is part of the php-circuit-breaker package.
 * 
 * @link https://github.com/ejsmont-artur/php-circuit-breaker
 * @link http://artur.ejsmont.org/blog/circuit-breaker
 * @author Artur Ejsmont
 *
 * For the full copyright and license information, please view the LICENSE file.
 */

namespace DavidGoodwin\CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\Adapter\BaseAdapter;
use DavidGoodwin\CircuitBreaker\Storage\StorageException;

/**
 * Recommended adapter using APCu local shared memory cache.
 * Super fast, safe, always available (if installed).
 * Does not introduce remote point of failure.
 * Can be efficently used to load/save each attribute separately if you wish
 * 
 * @see Ejsmont\CircuitBreaker\Storage\StorageInterface
 * @package Ejsmont\CircuitBreaker\Components
 */
class ApcAdapter extends BaseAdapter {

    /**
     * Configure instance
     * 
     * @param Integer $ttl          How long should circuit breaker data persist (between updates)
     * @param String  $cachePrefix  Value has to be string. If empty default cache key prefix is used.
     */
    public function __construct($ttl = 3600, $cachePrefix = false) {
        parent::__construct($ttl, $cachePrefix);
    }

    /**
     * Helper method to make sure that APCu extension is loaded
     * 
     * @throws Ejsmont\CircuitBreaker\Storage\StorageException if APCu is not loaded
     * @return void
     */
    protected function checkExtension() {
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
     * @throws Ejsmont\CircuitBreaker\Storage\StorageException if storage error occurs, handler can not be used
     */
    protected function load($key) {
        return apcu_fetch($key);
    }

    /**
     * Save item in the cache.
     * 
     * @param string $key
     * @param string $value
     * @param int $ttl
     * @return void
     * 
     * @throws Ejsmont\CircuitBreaker\Storage\StorageException if storage error occurs, handler can not be used
     */
    protected function save($key, $value, $ttl) {
        $result = apcu_store($key, $value, $ttl);
        if ($result === false) {
            throw new StorageException("Failed to save apc key: $key");
        }
    }

}