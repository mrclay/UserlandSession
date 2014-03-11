<?php

namespace UserlandSession\Tests\Storage;

use UserlandSession\Handler\PdoHandler;
use UserlandSession\BuiltIns;

class PdoHandlerTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var PdoHandler
     */
    protected $handler;

    /**
     * @var string[]
     */
    protected $params;

    /**
     * @var \PDO
     */
    protected $pdo;

    function setUp() {
        BuiltIns::reset();

        $this->params = (require __DIR__ . '/../../../db_params.php');

        $p = $this->params;
        $dsn = "{$p['driver']}:host={$p['host']};dbname={$p['dbname']};charset=UTF8";
        $username = $p['username'];
        $password = $p['password'];
        $this->pdo = new \PDO($dsn, $username, $password);

        $this->handler = new PdoHandler(array(
            'table' => $this->params['table'],
            'pdo' => $this->pdo,
        ));
        $this->handler->open(null, 'name');
    }

    function tearDown() {
        $this->pdo->query("TRUNCATE TABLE `{$this->params['table']}`");
        BuiltIns::reset();
    }

    function testParamsConstructor() {
        $p = $this->params;
        $storage = new PdoHandler(array(
            'table' => $p['table'],
            'dsn' => "{$p['driver']}:host={$p['host']};dbname={$p['dbname']};charset=UTF8",
            'username' => $p['username'],
            'password' => $p['password'],
        ));
        $storage->open(null, 'name');
        $storage->write('foo', 'bar');
        $this->assertSame('bar', $storage->read('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testMissingTable() {
        new PdoHandler(array(
            'pdo' => $this->pdo,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testBadPdoType() {
        new PdoHandler(array(
            'table' => $this->params['table'],
            'pdo' => 'not a PDO',
        ));
    }

    function testOpen() {
        $this->handler->close();
        $this->assertTrue($this->handler->open(null, 'name'));
    }

    function testClose() {
        $this->assertTrue($this->handler->close());
    }

    function testReadWrite() {
        $this->assertFalse($this->handler->read('foo'));

        $this->handler->write('foo', 'bar');
        $this->assertSame('bar', $this->handler->read('foo'));
    }

    function testDestroy() {
        $this->handler->write('foo', 'bar');

        $this->assertFalse($this->handler->destroy('goo'));
        $this->assertTrue($this->handler->destroy('foo'));
        $this->assertFalse($this->handler->read('foo'));
    }

    function testGc() {
        BuiltIns::getInstance()->timeOffset = -3600;
        $this->handler->write('60mago', 'bar');

        BuiltIns::getInstance()->timeOffset = -1800;
        $this->handler->write('30mago', 'bar');

        BuiltIns::getInstance()->timeOffset = 0;
        $this->handler->write('now', 'bar');

        $this->handler->gc(3000);
        $this->assertFalse($this->handler->read('60mago'));
        $this->assertSame('bar', $this->handler->read('30mago'));
        $this->assertSame('bar', $this->handler->read('now'));

        $this->handler->gc(900);
        $this->assertFalse($this->handler->read('30mago'));
        $this->assertSame('bar', $this->handler->read('now'));

        BuiltIns::getInstance()->timeOffset = 30;
        $this->handler->gc(0);
        $this->assertFalse($this->handler->read('now'));
    }

    function testBinaryData() {
        $expected = array(
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
        $this->handler->write('foo', serialize($expected));

        $returned = unserialize($this->handler->read('foo'));

        $this->assertSame($expected, $returned);
    }
}
