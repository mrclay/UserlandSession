<?php

namespace UserlandSession\Tests\Storage;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use UserlandSession\Session;
use UserlandSession\Handler\FileHandler;
use UserlandSession\Testing;

class FileStorageTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    /**
     * @var FileHandler
     */
    protected $storage;

    function setUp() {
        Testing::reset();
        $this->root = vfsStream::setup();
        $this->storage = new FileHandler('name', array(
            'path' => vfsStream::url('root'),
            'flock' => false,
        ));
    }

    function tearDown() {
        Testing::reset();
    }

    function testDefaults() {
        ini_set('session.save_path', '5;/tmp/');
        $obj = new FileHandler();

        $this->assertSame(Session::DEFAULT_SESSION_NAME, $obj->getName());
        $this->assertSame('/tmp', $obj->getPath());
    }

    function testSetPath() {
        $this->assertSame('vfs://root', $this->storage->getPath());
    }

    function testOpen() {
        $this->assertTrue($this->storage->open());
    }

    function testClose() {
        $this->assertTrue($this->storage->close());
    }

    function testReadWrite() {
        $this->assertFalse($this->storage->read('foo'));
        $filename = 'name_foo';
        $this->assertFalse($this->root->hasChild($filename));

        $this->storage->write('foo', 'bar');
        $this->assertSame('bar', $this->storage->read('foo'));
        $this->assertTrue($this->root->hasChild($filename));
    }

    function testDestroy() {
        $this->storage->write('foo', 'bar');
        $filename = 'name_foo';

        $this->assertFalse($this->storage->destroy('goo'));
        $this->assertTrue($this->storage->destroy('foo'));
        $this->assertFalse($this->storage->read('foo'));
        $this->assertFalse($this->root->hasChild($filename));
    }

    function testGc() {
        $this->storage->write('60mago', 'bar');
        $this->storage->write('30mago', 'bar');
        $this->storage->write('now', 'bar');

        $this->touchSessionFile('60mago', -3600);
        $this->touchSessionFile('30mago', -1800);

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

    /**
     * @expectedException \InvalidArgumentException
     */
    function testUnwritablePath() {
        $this->root->chown(vfsStream::OWNER_USER_2);
        $this->root->chmod(0700);

        new FileHandler('name', array(
            'path' => vfsStream::url('root'),
            'flock' => false,
        ));
    }

    protected function touchSessionFile($id, $time = 0) {
        $time = ($time > 0) ? $time : (\time() + $time);
        $filename = $this->storage->getPath() . "/" . $this->storage->getName() . "_$id";
        touch($filename, $time);
        clearstatcache();
    }
}
