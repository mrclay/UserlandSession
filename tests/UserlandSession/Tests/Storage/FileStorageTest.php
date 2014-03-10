<?php

namespace UserlandSession\Tests\Storage;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use UserlandSession\Storage\FileStorage;
use UserlandSession\Testing;

class FileStorageTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    /**
     * @var FileStorage
     */
    protected $fs;

    function setUp() {
        Testing::reset();
        $this->root = vfsStream::setup();
        $this->fs = new FileStorage('name', array(
            'path' => vfsStream::url('root'),
            'flock' => false,
        ));
    }

    function tearDown() {
        Testing::reset();
    }

    function testDefaults() {
        ini_set('session.save_path', '5;/tmp/');
        $obj = new FileStorage();

        $this->assertSame('name', $obj->getName());
        $this->assertSame('/tmp', $obj->getPath());
    }

    function testSetPath() {
        $this->assertSame('vfs://root', $this->fs->getPath());
    }

    function testOpen() {
        $this->assertTrue($this->fs->open());
    }

    function testClose() {
        $this->assertTrue($this->fs->close());
    }

    function testReadWrite() {
        $this->assertFalse($this->fs->read('foo'));
        $filename = 'name_foo';
        $this->assertFalse($this->root->hasChild($filename));

        $this->fs->write('foo', 'bar');
        $this->assertSame('bar', $this->fs->read('foo'));
        $this->assertTrue($this->root->hasChild($filename));
    }

    function testDestroy() {
        $this->fs->write('foo', 'bar');
        $filename = 'name_foo';

        $this->assertFalse($this->fs->destroy('goo'));
        $this->assertTrue($this->fs->destroy('foo'));
        $this->assertFalse($this->fs->read('foo'));
        $this->assertFalse($this->root->hasChild($filename));
    }

    function testGc() {
        $this->fs->write('60mago', 'bar');
        $this->fs->write('30mago', 'bar');
        $this->fs->write('now', 'bar');

        $this->touchSessionFile('60mago', -3600);
        $this->touchSessionFile('30mago', -1800);

        $this->fs->gc(3000);
        $this->assertFalse($this->fs->read('60mago'));
        $this->assertSame('bar', $this->fs->read('30mago'));
        $this->assertSame('bar', $this->fs->read('now'));

        $this->fs->gc(900);
        $this->assertFalse($this->fs->read('30mago'));
        $this->assertSame('bar', $this->fs->read('now'));

        Testing::getInstance()->timeOffset = 30;
        $this->fs->gc(0);
        $this->assertFalse($this->fs->read('now'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testUnwritablePath() {
        $this->root->chown(vfsStream::OWNER_USER_2);
        $this->root->chmod(0700);

        new FileStorage('name', array(
            'path' => vfsStream::url('root'),
            'flock' => false,
        ));
    }

    protected function touchSessionFile($id, $time = 0) {
        $time = ($time > 0) ? $time : (\time() + $time);
        $filename = $this->fs->getPath() . "/" . $this->fs->getName() . "_$id";
        touch($filename, $time);
        clearstatcache();
    }
}
