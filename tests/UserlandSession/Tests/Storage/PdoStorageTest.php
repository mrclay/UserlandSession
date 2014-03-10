<?php

namespace UserlandSession\Tests\Storage;

use UserlandSession\Handler\PdoHandler;
use UserlandSession\Testing;

class PdoStorageTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var PdoHandler
     */
    protected $storage;

    /**
     * @var string[]
     */
    protected $params;

    /**
     * @var \PDO
     */
    protected $pdo;

    function setUp() {
        Testing::reset();

        $this->params = (require __DIR__ . '/../../../db_params.php');

        $p = $this->params;
        $dsn = "{$p['driver']}:host={$p['host']};dbname={$p['dbname']};charset=UTF8";
        $username = $p['username'];
        $password = $p['password'];
        $this->pdo = new \PDO($dsn, $username, $password);

        $this->storage = new PdoHandler('name', array(
            'table' => $this->params['table'],
            'pdo' => $this->pdo,
        ));
    }

    function tearDown() {
        $this->pdo->query("TRUNCATE TABLE `{$this->params['table']}`");
        Testing::reset();
    }

    function testPdoConstructor() {
        $this->storage->open();
        $this->storage->write('foo', 'bar');
        $this->assertSame('bar', $this->storage->read('foo'));
    }

    function testParamsConstructor() {
        $p = $this->params;
        $storage = new PdoHandler('name', array(
            'table' => $p['table'],
            'dsn' => "{$p['driver']}:host={$p['host']};dbname={$p['dbname']};charset=UTF8",
            'username' => $p['username'],
            'password' => $p['password'],
        ));
        $storage->open();
        $storage->write('foo', 'bar');
        $this->assertSame('bar', $storage->read('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testMissingTable() {
        new PdoHandler('name', array(
            'pdo' => $this->pdo,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testBadPdoType() {
        new PdoHandler('name', array(
            'table' => $this->params['table'],
            'pdo' => 'not a PDO',
        ));
    }

    function testOpen() {
        $this->assertTrue($this->storage->open());
    }

    function testClose() {
        $this->assertTrue($this->storage->close());
    }

    function testReadWrite() {
        $this->storage->open();
        $this->assertFalse($this->storage->read('foo'));

        $this->storage->write('foo', 'bar');
        $this->assertSame('bar', $this->storage->read('foo'));
    }

    function testDestroy() {
        $this->storage->open();
        $this->storage->write('foo', 'bar');

        $this->assertFalse($this->storage->destroy('goo'));
        $this->assertTrue($this->storage->destroy('foo'));
        $this->assertFalse($this->storage->read('foo'));
    }

    function testGc() {
        $this->storage->open();

        Testing::getInstance()->timeOffset = -3600;
        $this->storage->write('60mago', 'bar');

        Testing::getInstance()->timeOffset = -1800;
        $this->storage->write('30mago', 'bar');

        Testing::getInstance()->timeOffset = 0;
        $this->storage->write('now', 'bar');

        $this->storage->gc(3000);
        $this->assertFalse($this->storage->read('60mago'));
        $this->assertSame('bar', $this->storage->read('30mago'));
        $this->assertSame('bar', $this->storage->read('now'));

        $this->storage->gc(900);
        $this->assertFalse($this->storage->read('30mago'));
        $this->assertSame('bar', $this->storage->read('now'));

        Testing::getInstance()->timeOffset = 30;
        $this->storage->gc(0);
        $this->assertFalse($this->storage->read('now'));
    }

    function testBinaryData() {
        $this->storage->open();
        $data = array(
            'Valid ASCII' => "a",
            'Valid 2 Octet Sequence' => "\xc3\xb1",
            'Invalid 2 Octet Sequence' => "\xc3\x28",
            'Invalid Sequence Identifier' => "\xa0\xa1",
            'Valid 3 Octet Sequence' => "\xe2\x82\xa1",
            'Invalid 3 Octet Sequence (in 2nd Octet)' => "\xe2\x28\xa1",
            'Invalid 3 Octet Sequence (in 3rd Octet)' => "\xe2\x82\x28",
            'Valid 4 Octet Sequence' => "\xf0\x90\x8c\xbc",
            'Invalid 4 Octet Sequence (in 2nd Octet)' => "\xf0\x28\x8c\xbc",
            'Invalid 4 Octet Sequence (in 3rd Octet)' => "\xf0\x90\x28\xbc",
            'Invalid 4 Octet Sequence (in 4th Octet)' => "\xf0\x28\x8c\x28",
            'Valid 5 Octet Sequence (but not Unicode!)' => "\xf8\xa1\xa1\xa1\xa1",
            'Valid 6 Octet Sequence (but not Unicode!)' => "\xfc\xa1\xa1\xa1\xa1\xa1",
        );
        $this->storage->write('foo', serialize($data));

        $unserialized = unserialize($this->storage->read('foo'));

        $this->assertSame($data, $unserialized);
    }
}
