<?php

namespace CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\Adapter\StashAdapter;
use PHPUnit\Framework\TestCase;

class StashAdapterTest extends TestCase
{
    private StashAdapter $adapter;

    private \Stash\Pool $pool;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('\Stash\Pool')) {
            $this->markTestSkipped("Stash not available.");
        }

        $this->pool = new \Stash\Pool(new \Stash\Driver\Ephemeral());
        $this->adapter = new StashAdapter($this->pool);
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

        $this->pool->clear();

        $this->assertEquals("", $this->adapter->loadStatus('AAA', 'BBB'));
    }

    public function testNonInstance()
    {
        $x = rand(1, 1000000);
        $this->adapter->saveStatus('A', 'BBB', $x);
        // make separate instance of clien and check if you can read through it
        $inst = new StashAdapter($this->pool);
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
        $this->pool->clear();
        $this->assertEquals("", $this->adapter->loadStatus('GGG', ''));
        $this->assertEquals("", $this->adapter->loadStatus('AAA', 'bbb'));
        $this->adapter->saveStatus('B', 'bbb', "");
        $this->assertEquals("", $this->adapter->loadStatus('A', 'bbb'), 6);
        $this->assertEquals("", $this->adapter->loadStatus('B', 'bbb'), 7);
    }

    public function testPrefix()
    {
        $adapter1 = new StashAdapter($this->pool);
        $adapter2 = new StashAdapter($this->pool, 1000, 'CircuitBreaker');
        $adapter3 = new StashAdapter($this->pool, 1000, 'CircuitWrong');

        $adapter1->saveStatus('abc', 'def', 951);

        $this->assertEquals(951, $adapter2->loadStatus('abc', 'def'));
        $this->assertEquals("", $adapter3->loadStatus('abc', 'def'));
    }

    public function testFailSave()
    {
        $this->expectException(\DavidGoodwin\CircuitBreaker\Storage\StorageException::class);

        $stashMock = $this->createMock('\Stash\Pool');
        $stashMock->expects($this->once())->method("getItem")->will($this->throwException(new \Exception("some error")));

        $adapter = new StashAdapter($stashMock);
        $adapter->saveStatus('someService', 'someValue', 951);
    }

    public function testFailLoad()
    {
        $this->expectException(\DavidGoodwin\CircuitBreaker\Storage\StorageException::class);

        $stashMock = $this->createMock('\Stash\Pool');
        $stashMock->expects($this->once())->method("getItem")->will($this->throwException(new \Exception("some error")));

        $adapter = new StashAdapter($stashMock);
        $adapter->loadStatus('someService', 'someValue');
    }
}
