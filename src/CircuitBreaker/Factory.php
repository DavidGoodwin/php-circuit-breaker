<?php

namespace DavidGoodwin\CircuitBreaker;

use DavidGoodwin\CircuitBreaker\Core\CircuitBreaker;
use DavidGoodwin\CircuitBreaker\Storage\Adapter\ApcAdapter;
use DavidGoodwin\CircuitBreaker\Storage\Adapter\DummyAdapter;
use DavidGoodwin\CircuitBreaker\Storage\Adapter\MemcachedAdapter;
use DavidGoodwin\CircuitBreaker\Storage\Decorator\ArrayDecorator;

/**
 * Allows easy assembly of circuit breaker instances.
 *
 * @see CircuitBreakerInterface
 */
class Factory
{
    /**
     * Creates a circuit breaker with same settings for all services using raw APC cache key.
     * APC raw adapter is faster than when wrapped with array decorator as APC uses direct memory access.
     *
     * @param int $maxFailures how many times do we allow service to fail before considering it offline
     * @param int $retryTimeout how many seconds should we wait before attempting retry
     *
     * @return CircuitBreakerInterface
     */
    public static function getSingleApcInstance(int $maxFailures = 20, int $retryTimeout = 30): CircuitBreakerInterface
    {
        $storage = new ApcAdapter();
        return new CircuitBreaker($storage, $maxFailures, $retryTimeout);
    }

    /**
     * Creates a circuit breaker using php array() as storage.
     * This instance looses the state when script execution ends.
     * Useful for testing and/or extremely long running backend scripts.
     *
     * @param int $maxFailures how many times do we allow service to fail before considering it offline
     * @param int $retryTimeout how many seconds should we wait before attempting retry
     *
     * @return CircuitBreakerInterface
     */
    public static function getDummyInstance(int $maxFailures = 20, int $retryTimeout = 30): CircuitBreakerInterface
    {
        $storage = new DummyAdapter();
        return new CircuitBreaker($storage, $maxFailures, $retryTimeout);
    }

    /**
     * Creates a circuit breaker with same settings for all services using memcached instance as a backend
     *
     * @param \Memcached $memcached instance of a connected Memcached object
     * @param int $maxFailures how many times do we allow service to fail before considering it offline
     * @param int $retryTimeout how many seconds should we wait before attempting retry
     *
     * @return CircuitBreakerInterface
     */
    public static function getMemcachedInstance(\Memcached $memcached, int $maxFailures = 20, int $retryTimeout = 30): CircuitBreakerInterface
    {
        $storage = new ArrayDecorator(new MemcachedAdapter($memcached));
        return new CircuitBreaker($storage, $maxFailures, $retryTimeout);
    }
}
