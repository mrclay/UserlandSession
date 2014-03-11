<?php

namespace UserlandSession\Tests\Storage;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use UserlandSession\Handler\FileHandler;
use UserlandSession\BuiltIns;

class FileHandlerTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    /**
     * @var FileHandler
     */
    protected $handler;

    function setUp() {
        BuiltIns::reset();
        $this->root = vfsStream::setup();
        $this->handler = new FileHandler(false);
        $this->handler->open($this->root->url(), 'name');
    }

    function tearDown() {
        BuiltIns::reset();
    }

    function testOpen() {
        $this->handler->close();
        $this->assertTrue($this->handler->open($this->root->url(), 'name'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testUnwritablePath() {
        $this->root->chown(vfsStream::OWNER_USER_2);
        $this->root->chmod(0700);

        $this->handler->close();
        $this->handler->open(vfsStream::url('root'), 'name');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testEmptyPath() {
        $this->handler->close();
        $this->handler->open('', 'name');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testPathWithTooManySemicolons() {
        $this->handler->close();
        $this->handler->open('5;0777;la;' . $this->root->url(), 'name');
    }

    function testExtraPathOptionsStripped() {
        $this->handler->close();
        $this->handler->open('5;0777;' . $this->root->url(), 'name');
        $this->handler->write('foo', 'bar');
        $this->assertSame('bar', $this->getFooContent());
    }

    function testReadWrite() {
        $this->assertFalse($this->handler->read('foo'));

        $this->handler->write('foo', 'bar');
        $this->assertSame('bar', $this->handler->read('foo'));
        $this->assertTrue((bool)$this->getFooContent());
    }

    function testClose() {
        $this->assertTrue($this->handler->close());
    }

    function testDestroy() {
        $this->handler->write('foo', 'bar');

        $this->assertFalse($this->handler->destroy('goo'));
        $this->assertTrue($this->handler->destroy('foo'));
        $this->assertFalse($this->handler->read('foo'));
        $this->assertNull($this->getFooContent());
    }

    function testGc() {
        $this->handler->write('60mago', 'bar');
        $this->handler->write('30mago', 'bar');
        $this->handler->write('now', 'bar');

        $this->touchSessionFile('60mago', -3600);
        $this->touchSessionFile('30mago', -1800);

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

    /**
     * @return null|string
     */
    protected function getFooContent() {
        $foo = $this->root->getChild('name_foo');
        if (!$foo) {
            return null;
        }
        return file_get_contents($foo->url());
    }

    protected function touchSessionFile($id, $time = 0) {
        $time = ($time > 0) ? $time : (\time() + $time);
        $filename = $this->root->url() . "/name_$id";
        touch($filename, $time);
        clearstatcache();
    }
}
