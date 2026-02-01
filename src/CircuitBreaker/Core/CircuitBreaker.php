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

namespace DavidGoodwin\CircuitBreaker\Core;

use DavidGoodwin\CircuitBreaker\CircuitBreakerInterface;
use DavidGoodwin\CircuitBreaker\Storage\StorageInterface;
use DavidGoodwin\CircuitBreaker\TrippedHandlerInterface;

/**
 * Allows user code to track the availability of any service by serviceName.
 *
 * @see CircuitBreakerInterface
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    protected StorageInterface $storageAdapter;

    /**
     * @var int default threshold, if service fails this many times will be disabled
     */
    protected int $defaultMaxFailures;

    /**
     * @var int  how many seconds should we wait before retry
     */
    protected int $defaultRetryTimeout;

    /**
     * Array with configuration per service name, format:
     *  array(
     *      "serviceName1" => array('maxFailures' => X, 'retryTimeout => Y),
     *      "serviceName2" => array('maxFailures' => X, 'retryTimeout => Y),
     *  )
     *
     * @var array settings per service name
     */
    protected array $settings = [];

    /**
     * Array of TrippedHandlerInterfaces
     * @var array
     */
    protected array $tripHandler = [];

    protected string $unavailableMessage = "Service No Longer Available";

    protected string $retryMessage = "Retrying Service";

    /**
     * Configure an instance with storage implementation and default threshold and retry timeout.
     *
     * @param StorageInterface $storage storage implementation
     * @param int $maxFailures default threshold, if service fails this many times will be disabled
     * @param int $retryTimeout how many seconds should we wait before retry
     */
    public function __construct(StorageInterface $storage, int $maxFailures = 20, int $retryTimeout = 60)
    {
        $this->storageAdapter = $storage;
        $this->defaultMaxFailures = $maxFailures;
        $this->defaultRetryTimeout = $retryTimeout;
    }

    /**
     * Register a Handler for a Service
     * @param string $serviceName
     * @param TrippedHandlerInterface $handlerInterface
     */
    public function registerHandler(string $serviceName, TrippedHandlerInterface $handlerInterface): void
    {
        $this->tripHandler[$serviceName] = $handlerInterface;
    }


    public function getUnavailableMessage(): string
    {
        return $this->unavailableMessage;
    }


    public function setUnavailableMessage(string $unavailableMessage): void
    {
        $this->unavailableMessage = $unavailableMessage;
    }

    public function getRetryMessage(): string
    {
        return $this->retryMessage;
    }

    public function setRetryMessage(string $retryMessage): void
    {
        $this->retryMessage = $retryMessage;
    }


    /**
     * Use this method only if you want to add server specific threshold and retry timeout.
     *
     * @param String $serviceName service
     * @param int $maxFailures default threshold, if service fails this many times will be disabled
     * @param int $retryTimeout how many seconds should we wait before retry
     * @return CircuitBreaker
     */
    public function setServiceSettings(string $serviceName, int $maxFailures, int $retryTimeout)
    {
        $this->settings[$serviceName] = array(
            'maxFailures' => $maxFailures ? $maxFailures : $this->defaultMaxFailures,
            'retryTimeout' => $retryTimeout ? $retryTimeout : $this->defaultRetryTimeout,
        );
        return $this;
    }

    // ---------------------- HELPERS -----------------

    /**
     * Load setting or initialise service name with defaults for faster lookups
     *
     * @param string $serviceName what service to look for
     * @param string $variable what setting to look for
     * @return int
     */
    private function getSetting(string $serviceName, string $variable)
    {
        // make sure there are settings for the service
        if (!isset($this->settings[$serviceName])) {
            $this->settings[$serviceName] = array(
                'maxFailures' => $this->defaultMaxFailures,
                'retryTimeout' => $this->defaultRetryTimeout,
            );
        }
        return $this->settings[$serviceName][$variable];
    }

    protected function getMaxFailures(string $serviceName): int
    {
        return $this->getSetting($serviceName, 'maxFailures');
    }

    protected function getRetryTimeout(string $serviceName): int
    {
        return $this->getSetting($serviceName, 'retryTimeout');
    }

    protected function getFailures(string $serviceName): int
    {

        return (int)$this->storageAdapter->loadStatus($serviceName, 'failures');
    }

    protected function getLastTest(string $serviceName): int
    {
        return (int)$this->storageAdapter->loadStatus($serviceName, 'lastTest');
    }

    /**
     * TODO Remove reference to time() replace with DateTime object
     * @param $serviceName
     * @param $newValue
     */
    protected function setFailures(string $serviceName, int $newValue): void
    {
        $this->storageAdapter->saveStatus($serviceName, 'failures', (string)$newValue, false);
        // make sure storage adapter flushes changes this time
        $this->storageAdapter->saveStatus($serviceName, 'lastTest', (string)time(), true);
    }

    public function isAvailable(string $serviceName): bool
    {
        $failures = $this->getFailures($serviceName);
        $maxFailures = $this->getMaxFailures($serviceName);
        if ($failures < $maxFailures) {
            // this is what happens most of the time so we evaluate first
            return true;
        } else {
            // This code block will execute a handler for tripping
            // Like the code block below there is still a race condition present so it will be possible for this code to
            // execute twice or more on extremely busy systems so please keep this in mind.
            if ($failures == $maxFailures && isset($this->tripHandler[$serviceName])) {
                $handler = $this->tripHandler[$serviceName] ?? null;
                if (!$handler instanceof TrippedHandlerInterface) {
                    throw new \InvalidArgumentException("Handler for service $serviceName has not been configured");
                }
                $handler($serviceName, $failures, $this->unavailableMessage);
            }

            $lastTest = $this->getLastTest($serviceName);
            $retryTimeout = $this->getRetryTimeout($serviceName);
            if ($lastTest + $retryTimeout < time()) {
                // Once we wait $retryTimeout, we have to allow one
                // thread to try to connect again. To prevent all other threads
                // from flooding, the potentially dead db, we update the time first
                // and then try to connect. If db is dead only one thread will hang
                // waiting for the connection. Others will get updated timeout from stats.
                //
                // 'Race condition' is between first thread getting into this line and
                // time it takes to store the settings. In that time other threads will
                // also be entering this statement. Even on very busy servers it
                // wont allow more than a few requests to get through before stats are updated.
                //
                // updating lastTest
                $this->setFailures($serviceName, $failures);

                //Lets handle the retry
                if (isset($this->tripHandler[$serviceName])) {
                    $handler = $this->tripHandler[$serviceName];
                    $handler($serviceName, $failures, $this->retryMessage);
                }

                // allowing this thread to try to connect to the resource
                return true;
            } else {
                return false;
            }
        }
    }

    /*
     * @see Zend_CircuitBreaker_Interface
     */

    public function reportFailure(string $serviceName): void
    {
        // there is no science here, we always increase failures count
        $this->setFailures($serviceName, $this->getFailures($serviceName) + 1);
    }

    /*
     * @see Zend_CircuitBreaker_Interface
     */

    public function reportSuccess(string $serviceName): void
    {
        $failures = $this->getFailures($serviceName);
        $maxFailures = $this->getMaxFailures($serviceName);
        if ($failures > $maxFailures) {
            // there were more failures than max failures
            // we have to reset failures count to max-1
            $this->setFailures($serviceName, $maxFailures - 1);
        } elseif ($failures > 0) {
            // if we are between max and 0 we decrease by 1 on each
            // success so we will go down to 0 after some time
            // but we are still more sensitive to failures
            $this->setFailures($serviceName, $failures - 1);
        } else {
            // if there are no failures reported we do not
            // have to do anything on success (system operational)
        }
    }

    /**
     * Quick and dirty way to use the breaker
     *
     * @param string   $serviceName
     * @param \Closure $code
     * @param \Closure $failed
     */
    public function attempt(string $serviceName, \Closure $code, \Closure $failed): void
    {
        if ($this->isAvailable($serviceName)) {
            try {
                $code();
                $this->reportSuccess($serviceName);
            } catch (\Exception $e) {
                $this->reportFailure($serviceName);
                $failed();
            }
        } else {
            $failed();
        }
    }
}
