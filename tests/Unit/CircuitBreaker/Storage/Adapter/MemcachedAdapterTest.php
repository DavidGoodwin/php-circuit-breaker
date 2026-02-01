<?php

namespace CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\Adapter\MemcachedAdapter;
use PHPUnit\Framework\TestCase;

class MemcachedAdapterTest extends TestCase
{
    private MemcachedAdapter $adapter;

    private \Memcached $connection;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('\Memcached')) {
            $this->markTestSkipped("extension not loaded");
        }
        $this->connection = new \Memcached();
        $this->connection->addServer("localhost", 11211);
        $this->adapter = new MemcachedAdapter($this->connection);
    }

    protected function tearDown(): void
    {
        $this->adapter = null;
        parent::tearDown();
    }

    public function testSave()
    {
        $x = "val";
        $this->adapter->saveStatus('AAA', 'BBB', $x);
        $this->assertEquals("val", $this->adapter->loadStatus('AAA', 'BBB'));
    }

    public function testSaveEmpty()
    {
        $x = "";
        $this->adapter->saveStatus('X', 'BBB', $x);
        $this->assertEquals("", $this->adapter->loadStatus('X', 'BBB'));
    }

    public function testSaveClear()
    {
        $x = "valB";
        $this->adapter->saveStatus('AAA', 'BBB', $x);
        $this->connection->flush();

        $this->assertEquals("", $this->adapter->loadStatus('AAA', 'BBB'));
    }

    public function testNonInstance()
    {
        $x = rand(1, 1000000);
        $this->adapter->saveStatus('A', 'BBB', $x);
        // make separate instance of clien and check if you can read through it
        $inst = new MemcachedAdapter($this->connection);
        $this->assertEquals($x, $inst->loadStatus('A', 'BBB'));
    }

    public function testLoadStatusSimple()
    {
        $x = 'abcde';
        $this->adapter->saveStatus('AAA', 'bbb', $x);
        $this->assertEquals("", $this->adapter->loadStatus('AAa', 'bbb'));
        $this->assertEquals("", $this->adapter->loadStatus('AA', 'bbb'));
        $this->assertEquals("", $this->adapter->loadStatus('AAAA', 'bbb'));
        $this->assertEquals('abcde', $this->adapter->loadStatus('AAA', 'bbb'));
    }

    public function testLoadStatusEmpty()
    {
        $this->connection->delete('CircuitBreakerAAAbbb');
        $this->assertEquals("", $this->adapter->loadStatus('GGG', ''));
        $this->assertEquals("", $this->adapter->loadStatus('AAA', 'bbb'));
        $this->adapter->saveStatus('B', 'bbb', "");
        $this->assertEquals("", $this->adapter->loadStatus('A', 'bbb'), 6);
        $this->assertEquals("", $this->adapter->loadStatus('B', 'bbb'), 7);
    }

    public function testPrefix()
    {
        $adapter1 = new MemcachedAdapter($this->connection);
        $adapter2 = new MemcachedAdapter($this->connection, 1000, 'CircuitBreaker');
        $adapter3 = new MemcachedAdapter($this->connection, 1000, 'CircuitWrong');

        $adapter1->saveStatus('abc', 'def', 951);

        $this->assertEquals(951, $adapter2->loadStatus('abc', 'def'));
        $this->assertEquals("", $adapter3->loadStatus('abc', 'def'));
    }

    public function testFailSave()
    {
        $this->expectException(\DavidGoodwin\CircuitBreaker\Storage\StorageException::class);

        $memcachedMock = $this->createMock(\Memcached::class);
        $memcachedMock->expects($this->once())->method("set")->will($this->throwException(new \Exception("some error")));

        $adapter = new MemcachedAdapter($memcachedMock);
        $adapter->saveStatus('someService', 'someValue', 951);
    }

    public function testFailLoad()
    {
        $this->expectException(\DavidGoodwin\CircuitBreaker\Storage\StorageException::class);

        $memcachedMock = $this->createMock(\Memcached::class);
        $memcachedMock->expects($this->once())->method("get")->will($this->throwException(new \Exception("some error")));

        $adapter = new MemcachedAdapter($memcachedMock);
        $adapter->loadStatus('someService', 'someValue');
    }
}
