<?php

namespace CircuitBreaker;

use DavidGoodwin\CircuitBreaker\Core\CircuitBreaker;
use DavidGoodwin\CircuitBreaker\Storage\Adapter\DummyAdapter;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    private DummyAdapter $adapter;

    private CircuitBreaker $circuitBreaker;

    /** @var array */
    private array $conf = array(
        "dbKnown" => array('maxFailures' => 5, 'retryTimeout' => 5),
        "dbWrong" => array('maxFailures' => 0, 'retryTimeout' => 0),
    );

    protected function setUp(): void
    {

        parent::setUp();
        $this->adapter = new DummyAdapter();
        $this->circuitBreaker = new CircuitBreaker($this->adapter);

        foreach ($this->conf as $serviceName => $config) {
            $this->circuitBreaker->setServiceSettings($serviceName, $config['maxFailures'], $config['retryTimeout']);
        }
    }

    public function testOk()
    {
        $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbKnown'));
        $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbWrong'));
        $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbNew'));
    }

    public function testKnown()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbKnown'), 1);
            $this->circuitBreaker->reportFailure('dbKnown');
        }

        for ($i = 0; $i < 20; $i++) {
            $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbKnown'), "attempt 2:" . $i);
            $this->circuitBreaker->reportFailure('dbKnown');
        }
        $this->assertEquals(25, $this->adapter->loadStatus('dbKnown', 'failures'));
        $this->assertTrue(time() - $this->adapter->loadStatus('dbKnown', 'lastTest') < 2);
    }

    public function testWrong()
    {
        for ($i = 0; $i < 20; $i++) {
            $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbWrong'));
            $this->circuitBreaker->reportFailure('dbWrong');
        }
        for ($i = 0; $i < 20; $i++) {
            $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbWrong'));
            $this->circuitBreaker->reportFailure('dbWrong');
        }
        $this->assertEquals(40, $this->adapter->loadStatus('dbWrong', 'failures'));
    }

    public function testNew()
    {
        for ($i = 0; $i < 20; $i++) {
            $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbNew'));
            $this->circuitBreaker->reportFailure('dbNew');
        }
        for ($i = 0; $i < 25; $i++) {
            $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbNew'));
            $this->circuitBreaker->reportFailure('dbNew');
        }
        $this->assertEquals(45, $this->adapter->loadStatus('dbNew', 'failures'));
    }

    public function testAllOk()
    {
        $this->circuitBreaker->reportSuccess('dbKnown');
        $value = $this->adapter->loadStatus('dbNew', 'failures');
        $this->assertTrue(empty($value));
    }

    public function testAllZero()
    {
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportSuccess('dbKnown');
        $this->assertEquals(0, $this->adapter->loadStatus('dbKnown', 'failures'));
    }

    public function testAllOne()
    {
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportSuccess('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->assertEquals(1, $this->adapter->loadStatus('dbKnown', 'failures'));
    }

    public function testAllSix()
    {
        $this->circuitBreaker->reportSuccess('dbKnown');
        $this->circuitBreaker->reportSuccess('dbKnown');
        $this->circuitBreaker->reportSuccess('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->assertEquals(6, $this->adapter->loadStatus('dbKnown', 'failures'));
        $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbKnown'));
    }

    public function testAllStacking()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbKnown'));
            $this->circuitBreaker->reportFailure('dbKnown');
        }
        $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbKnown'));
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbKnown'));
        // reset to max-1
        $this->circuitBreaker->reportSuccess('dbKnown');
        $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbKnown'));
        // go over max again
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbKnown'));
        $this->circuitBreaker->reportSuccess('dbKnown');
        $this->circuitBreaker->reportSuccess('dbKnown');
        $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbKnown'));
    }

    public function testAllRetry()
    {
        // initiate with failure over 3s ago
        $last = time() - 6;
        $this->adapter->saveStatus('dbKnown', 'failures', 10);
        $this->adapter->saveStatus('dbKnown', 'lastTest', $last);

        // its 10 failures
        $this->assertEquals(10, $this->adapter->loadStatus('dbKnown', 'failures'));

        // retry timer elapsed so should allow us
        $this->assertEquals(true, $this->circuitBreaker->isAvailable('dbKnown'), 2);
        // no update so still valid
        $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbKnown'), 3);
        $this->circuitBreaker->reportFailure('dbKnown');
        $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbKnown'), 4);

        // its 11 now
        $this->assertEquals(11, $this->adapter->loadStatus('dbKnown', 'failures'));
    }

    public function testAllNoRetry()
    {
        // initiate with failure over 3s ago
        $last = time() - 4;
        $this->adapter->saveStatus('dbKnown', 'failures', 10);
        $this->adapter->saveStatus('dbKnown', 'lastTest', $last);
        $this->assertEquals(false, $this->circuitBreaker->isAvailable('dbKnown'), 2);
    }
}
