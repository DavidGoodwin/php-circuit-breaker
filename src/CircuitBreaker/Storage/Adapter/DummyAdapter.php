<?php

namespace DavidGoodwin\CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\StorageInterface;

/**
 * Does not really persist stats between requests!
 *
 * Can only be used for tests and as fallback instance.
 *
 * When real storage handler throws exception it means it cant be used any more.
 * Then storage user can safely fallback to this dummy instance.
 *
 * @see StorageInterface
 */
class DummyAdapter implements StorageInterface
{

    /**
     * @var mixed[] Array of all the values (transient)
     */
    protected array $data = array();

    /**
     * Loads circuit breaker service status value.
     *
     * @param string $serviceName name of service to load stats for
     * @param string $attributeName name of attribute to load
     * @return    string  value stored or '' if value was not found
     */
    public function loadStatus(string $serviceName, string $attributeName): string
    {
        if (isset($this->data[$serviceName][$attributeName])) {
            return $this->data[$serviceName][$attributeName];
        }
        return "";
    }

    /**
     * Saves circuit breaker service status value.
     *
     * @param string $serviceName name of service to load stats for
     * @param string $attributeName name of the attribute to load
     * @param string $value string value loaded or '' if nothing found
     * @param boolean $flush set to true will force immediate save, false does not guaranteed saving at all.
     * @return    void
     */
    public function saveStatus(string $serviceName, string $attributeName, string $value, bool $flush = false): void
    {
        $this->data[$serviceName][$attributeName] = $value;
    }

}