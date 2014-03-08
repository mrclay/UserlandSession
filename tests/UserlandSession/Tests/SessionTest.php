<?php

namespace UserlandSession\Tests;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use UserlandSession\Session;
use UserlandSession\Storage\FileStorage;
use UserlandSession\Testing;

class SessionTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var Session
     */
    protected $sess;

    /**
     * @var FileStorage
     */
    protected $storage;

    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    function setUp() {
        Testing::reset();
        $this->root = vfsStream::setup();
        $this->storage = new FileStorage('name', array(
            'path' => vfsStream::url('root'),
            'flock' => false,
        ));
        $this->sess = new Session($this->storage);
    }

    function tearDown() {
        Testing::reset();
    }

    function testNotStarted() {
        $this->assertSame('', $this->sess->id());
        $this->assertFalse($this->sess->set('foo', 'bar'));
        $this->assertFalse($this->sess->writeClose());
        $this->assertFalse($this->sess->destroy());
        $this->assertFalse($this->sess->regenerateId());
    }

    function testCantStart() {
        Testing::getInstance()->headers_sent = true;
        $this->assertFalse($this->sess->start());
    }

    /**
     * @expectedException \UserlandSession\Exception
     */
    function testInvalidStorage() {
        $storage = new FileStorage('...', array(
            'path' => vfsStream::url('root'),
            'flock' => false,
        ));
        new Session($storage);
    }

    function testGetStorage() {
        $this->assertSame($this->storage, $this->sess->getStorage());
    }

    function testFixationAttack() {
        $_COOKIE['name'] = 'abc123';
        $this->sess->start();
        $this->assertNotSame('name', $this->sess->id());
    }

    function testSessionSniffing() {
        $this->storage->write('abcde', 'notSerialization');
        $this->storage->write('12345', serialize(array('foo' => 'bar')));

        $cookieValues = array(
            array(null,            false,   false, null),
            array(array('1', '2'), false,   false, null),
            array('..',            false,   false, null),
            array('y78fy',         'y78fy', false, null),
            array('abcde',         'abcde', true,  array()),
            array('12345',         '12345', true,  array('foo' => 'bar')),
        );

        foreach ($cookieValues as $arr) {
            list($cookieId, $id, $likely, $data) = $arr;

            unset($_COOKIE['name']);
            if ($cookieId) {
                $_COOKIE['name'] = $cookieId;
            }

            $this->assertSame($id, $this->sess->getIdFromCookie());
            $this->assertSame($likely, $this->sess->sessionLikelyExists());

            if ($likely) {
                $this->sess->start();

                if ($data) {
                    $this->assertSame($id, $this->sess->id());
                    $this->assertSame('bar', $this->sess->get('foo'));
                } else {
                    $this->assertNotSame($id, $this->sess->id());
                    $this->assertSame(null, $this->sess->get('foo'));
                }

                $this->assertSame($data, $this->sess->data);

                $sessId = $this->sess->id();

                // @todo Why the WARNING from vfsStreamWrapper::open_stream, yet it writes the file correctly?
                // And why don't we get that warning in the FileStorage test?
                $this->sess->writeClose();

                $this->assertTrue((bool)file_get_contents($this->root->getChild("name_$sessId")->url()));
            }
        }
    }

    function testDefaultStartHeaders() {
        $this->sess->start();

        $cookie = array (
            'name' => 'name',
            'value' => $this->sess->id(),
            'expire' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
        );
        $headers = array (
            0 => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT',
            1 => 'Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
            2 => 'Pragma: no-cache',
        );
        $this->assertSame($cookie, Testing::getInstance()->cookiesSet[0]);
        $this->assertSame($headers, Testing::getInstance()->headersSet);
    }

    function testStartHeadersWithPublicCache() {
        Testing::getInstance()->fixedTime = 86400;
        $this->sess->cache_limiter = Session::CACHE_LIMITER_PUBLIC;
        $this->sess->start();

        $lastModified = Session::formatAsGmt(filemtime($_SERVER['SCRIPT_FILENAME']));
        $headers = array (
            0 => 'Expires: Fri, 02 Jan 1970 00:03:00 GMT',
            1 => 'Cache-Control: public, max-age=180',
            2 => "Last-Modified: $lastModified",
        );
        $cookie = array (
            'name' => 'name',
            'value' => $this->sess->id(),
            'expire' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
        );
        $this->assertSame($cookie, Testing::getInstance()->cookiesSet[0]);
        $this->assertSame($headers, Testing::getInstance()->headersSet);

//        var_export(Testing::getInstance()->headersSet);
//        var_export(Testing::getInstance()->cookiesSet);
    }

    function testStartHeaderPrivateNoExpire() {

    }

    function testStartHeaderPrivate() {

    }

    function testStartHeaderNoCacheLimiting() {

    }

    function testStartWithExistingSessionDoesntSetCookie() {

    }

    function testGc() {
        // @todo mock mt_rand
    }

    function testWriteClose() {
        // check saves data

        // check closes storage

        // check id() == ''
    }

    function testRegenerateId() {

    }
}
