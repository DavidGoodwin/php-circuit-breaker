<?php

namespace DavidGoodwin\CircuitBreaker\Storage\Decorator;

use DavidGoodwin\CircuitBreaker\Storage\StorageException;
use DavidGoodwin\CircuitBreaker\Storage\StorageInterface;

/**
 * Service status data can be aggregated into one array.
 * Especially useful if you are using remote storage like memcache.
 * Otherwise every service counter/time would have to be loaded separately.
 * Decorator will group updates until flush is requested.
 * On save flush decorator will load stats again and update them with new values.
 *
 * @see StorageInterface
 */
class ArrayDecorator implements StorageInterface
{

    /**
     * @var StorageInterface Storage handler that will be used to load and save the aggregated array.
     */
    protected $instance;

    /**
     * @var array Array of agregated service stats loaded from storage handler
     */
    protected ?array $stats = null;

    /**
     * @var array Array of stats that have been updated since last flush or since script processing began.
     */
    protected $dirtyStats = array();

    /**
     * @var string key to be used for the cache
     */
    protected $cacheKeyPrefix = 'CircuitBreakerStats';

    /**
     * @var string key to be used for the cache
     */
    protected $cacheKeySuffix = 'AggregatedStats';

    /**
     * Configure decorator instance
     *
     * @param StorageInterface $wrappedInstance instance of storage interface that will be used
     */
    public function __construct(StorageInterface $wrappedInstance)
    {
        $this->instance = $wrappedInstance;
    }

    /**
     * Loads circuit breaker service status values from array.
     * Only one remote call for all services.
     *
     * @param string $serviceName name of service to load stats for
     * @param string $attributeName name of attribute to load
     * @return    string  value stored or '' if value was not found
     *
     * @throws StorageException if storage error occurs, handler can not be used
     */
    public function loadStatus(string $serviceName, string $attributeName): string
    {
        // make sure we have the values loaded (request time cache of all service stats)
        $this->stats = $this->loadStatsArray();
        // return 
        return $this->stats[$serviceName][$attributeName] ?? '';
    }

    /**
     * We reload values just before saving to reduce race condition time.
     * The updated values are merged with previous values.
     * Then everything is stored back into the storage
     *
     * @param string $serviceName name of service to load stats for
     * @param string $attributeName name of the attribute to load
     * @param string $value string value loaded or '' if nothing found
     * @param boolean $flush set to true will force immediate save, false does not guaranteed saving at all.
     * @return    void
     *
     * @throws StorageException if storage error occurs, handler can not be used
     */
    public function saveStatus($serviceName, $attributeName, $value, $flush = false)
    {
        // before writing we load the data no matter what
        $this->stats = $this->loadStatsArray();

        // if this service is unknown add it to dirty array
        $this->dirtyStats[$serviceName][$attributeName] = $value;
        $this->stats[$serviceName][$attributeName] = $value;

        if ($flush) {
            // force reload stats
            $this->stats = $this->loadStatsArray();

            // merge all dirty stats into the oryginal array
            foreach ($this->dirtyStats as $service => $values) {
                foreach ($values as $name => $value) {
                    $this->stats[$service][$name] = $value;
                }
            }

            // force save
            $this->saveStatsArray();

            // next time we wont override these any more (they could change in the mean time)
            $this->dirtyStats = array();
        }
    }

    /**
     * Method that actually loads the stats array from wrapped instance.
     * Loads stats only if need to be loaded.
     *
     */
    protected function loadStatsArray(): array
    {
        $stats = $this->instance->loadStatus($this->cacheKeyPrefix, $this->cacheKeySuffix);
        if (!empty($stats)) {
            $stats = unserialize($stats);
        }

        // make sure unserialize and load were successfull and we have array
        if (!is_array($stats)) {
            $stats = array();
        }
        return $stats;

    }

    /**
     * Method that actually saves the stats array in wrapped instance.
     * Saves only if there is dirty service data.
     *
     * @return void
     */
    protected function saveStatsArray()
    {
        if (is_array($this->stats)) {
            $this->instance->saveStatus($this->cacheKeyPrefix, $this->cacheKeySuffix, serialize($this->stats), true);
        }
    }

}