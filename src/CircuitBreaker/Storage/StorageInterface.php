<?php

namespace DavidGoodwin\CircuitBreaker\Storage;

/**
 * Interface defines methods for storage adapters/decorators for the circuit breaker persistence.
 *
 * Minimalistic interface to allow easy integration with any persistence backend.
 */
interface StorageInterface
{
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
    public function loadStatus(string $serviceName, string $attributeName): string;

    /**
     * Saves circuit breaker service status value.
     * Method does not care what are the attribute names. They are not inspected.
     * Any string can be passed as service name and attribute name, value can be int/string.
     *
     * Saving in storage is not guaranteed unless flush is set to true.
     * Use calls without flush if you know you will update more than one value and you want to
     * improve performance of the calls.
     *
     * @param string $serviceName name of service to load stats for
     * @param string $attributeName name of the attribute to load
     * @param string $value string value loaded or '' if nothing found
     * @param boolean $flush set to true will force immediate save, false does not guaranteed saving at all.
     * @return    void
     *
     * @throws StorageException if storage error occurs, handler can not be used
     */
    public function saveStatus(string $serviceName, string $attributeName, string $value, bool $flush = false): void;
}
