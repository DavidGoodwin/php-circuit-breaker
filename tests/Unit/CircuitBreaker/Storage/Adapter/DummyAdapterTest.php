<?php

namespace CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\Adapter\DummyAdapter;
use PHPUnit\Framework\TestCase;

class DummyAdapterTest extends TestCase
{
    private $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new DummyAdapter();
    }

    public function testLoadStatusSimple()
    {
        $this->assertEquals("", $this->adapter->loadStatus('AAA', 'bbb'));
        $x = 'abcde';
        $this->adapter->saveStatus('AAA', 'bbb', $x);
        $this->assertEquals("", $this->adapter->loadStatus('AAa', 'bbb'));
        $this->assertEquals("", $this->adapter->loadStatus('AA', 'bbb'));
        $this->assertEquals("", $this->adapter->loadStatus('AAAA', 'bbb'));
        $this->assertEquals('abcde', $this->adapter->loadStatus('AAA', 'bbb'));
    }

    public function testLoadStatusEmpty()
    {
        $this->assertEquals("", $this->adapter->loadStatus('', 'bbb'));
        $this->assertEquals("", $this->adapter->loadStatus('', ''));
        $this->assertEquals("", $this->adapter->loadStatus('BBB', ''));
        $this->assertEquals("", $this->adapter->loadStatus('AAA', 'bbb'));
        $this->assertEquals("", $this->adapter->loadStatus('B', 'bbb'));
        $this->adapter->saveStatus('B', 'bbb', "");
        $this->assertEquals("", $this->adapter->loadStatus('A', 'bbb'));
        $this->assertEquals("", $this->adapter->loadStatus('B', 'bbb'));
    }
}
