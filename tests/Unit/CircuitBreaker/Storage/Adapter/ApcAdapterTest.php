<?php

namespace CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\Adapter\ApcAdapter;
use PHPUnit\Framework\TestCase;

class ApcAdapterTest extends TestCase
{
    private ApcAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('apcu_clear_cache')) {
            $this->markTestSkipped("APCu not installed");
        }

        if (ini_get('apc.enable_cli') === '0') {
            $this->markTestSkipped('APCu not enabled for CLI');
        }

        apcu_clear_cache();

        $this->adapter = new ApcAdapter();
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
        apcu_clear_cache();

        $this->assertEquals("", $this->adapter->loadStatus('AAA', 'BBB'));
    }

    public function testNonInstance()
    {
        $x = rand(1, 1000000);
        $this->adapter->saveStatus('A', 'BBB', $x);
        // make separate instance of clien and check if you can read through it
        $inst = new ApcAdapter();
        $this->assertEquals($x, $inst->loadStatus('A', 'BBB'));
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

    public function testPrefix()
    {
        $adapter1 = new ApcAdapter();
        $adapter2 = new ApcAdapter(1000, 'CircuitBreaker'); // default
        $adapter3 = new ApcAdapter(1000, 'CircuitWrong');

        $adapter1->saveStatus('abc', 'def', 951);

        $this->assertEquals(951, $adapter2->loadStatus('abc', 'def'));
        $this->assertEquals("", $adapter3->loadStatus('abc', 'def'));
    }
}
