<?php

namespace UserlandSession\Tests\Storage;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use UserlandSession\Session;
use UserlandSession\Storage\FileStorage;

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
        $this->root = vfsStream::setup();
        $this->fs = new FileStorage(Session::DEFAULT_SESSION_NAME, array(
            'path' => vfsStream::url('root'),
            'flock' => false,
        ));
    }

    function testDefaults() {
        ini_set('session.save_path', '5;/tmp/');
        $obj = new FileStorage();

        $this->assertSame(Session::DEFAULT_SESSION_NAME, $obj->getName());
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
        $filename = Session::DEFAULT_SESSION_NAME . '_foo';
        $this->assertFalse($this->root->hasChild($filename));

        $this->fs->write('foo', 'bar');
        $this->assertSame('bar', $this->fs->read('foo'));
        $this->assertTrue($this->root->hasChild($filename));
    }

    function testDestroy() {
        $this->fs->write('foo', 'bar');
        $filename = Session::DEFAULT_SESSION_NAME . '_foo';

        $this->assertFalse($this->fs->destroy('goo'));
        $this->assertTrue($this->fs->destroy('foo'));
        $this->assertFalse($this->fs->read('foo'));
        $this->assertFalse($this->root->hasChild($filename));
    }

    function testGc() {
        $this->fs->write('foo', 'bar');
        $this->fs->gc(30);
        $this->assertSame('bar', $this->fs->read('foo'));

        $filename = Session::DEFAULT_SESSION_NAME . '_foo';
        $file = $this->root->getChild($filename);

        touch($file->url(), 20);
        clearstatcache();

        $this->fs->gc(30);
        $this->assertFalse($this->fs->read('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testUnwritablePath() {
        $this->root->chown(vfsStream::OWNER_USER_2);
        $this->root->chmod(0600);

        new FileStorage(Session::DEFAULT_SESSION_NAME, array(
            'path' => vfsStream::url('root'),
            'flock' => false,
        ));
    }
}
