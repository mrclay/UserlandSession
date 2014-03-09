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
        $this->assertFalse($this->sess->writeClose());
        $this->assertFalse($this->sess->destroy());
        $this->assertFalse($this->sess->regenerateId());
    }

    function testCantStart() {
        Testing::getInstance()->headers_sent = true;
        $this->assertFalse($this->sess->start());
    }

    function testSuccessfulStartReturnsTrue() {
        $this->assertTrue($this->sess->start());
        $this->assertSame(array(), $this->sess->data);
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
            // cookie value        sniffed ID  likely exists  data
            array(null,            false,      false,         null),
            array(array('1', '2'), false,      false,         null),
            array('..',            false,      false,         null),
            array('y78fy',         'y78fy',    false,         null),
            array('abcde',         'abcde',    true,          array()),
            array('12345',         '12345',    true,          array('foo' => 'bar')),
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
        Testing::getInstance()->fixedTime = 96400;
        $this->sess->cache_limiter = Session::CACHE_LIMITER_PUBLIC;
        $this->sess->start();

        $lastModified = Session::formatAsGmt(filemtime($_SERVER['SCRIPT_FILENAME']));
        $headers = array (
            0 => 'Expires: Fri, 02 Jan 1970 02:49:40 GMT',
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

    }

    function testStartHeaderPrivateNoExpire() {
        Testing::getInstance()->fixedTime = 96400;
        $this->sess->cache_limiter = Session::CACHE_LIMITER_PRIVATE_NO_EXPIRE;
        $this->sess->start();

        $lastModified = Session::formatAsGmt(filemtime($_SERVER['SCRIPT_FILENAME']));
        $headers = array (
            0 => 'Cache-Control: private, max-age=180, pre-check=180',
            1 => "Last-Modified: $lastModified",
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
    }

    function testStartHeaderPrivate() {
        $this->sess->cache_limiter = Session::CACHE_LIMITER_PRIVATE;
        $this->sess->start();

        $lastModified = Session::formatAsGmt(filemtime($_SERVER['SCRIPT_FILENAME']));
        $headers = array (
            0 => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT',
            1 => 'Cache-Control: private, max-age=180, pre-check=180',
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
    }

    function testStartHeaderNoCacheLimiting() {
        $this->sess->cache_limiter = Session::CACHE_LIMITER_NONE;

        $this->sess->cookie_lifetime = 400;
        Testing::getInstance()->fixedTime = 86400;

        $this->sess->start();

        $headers = array();
        $cookie = array (
            'name' => 'name',
            'value' => $this->sess->id(),
            'expire' => 86800,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
        );
        $this->assertSame($cookie, Testing::getInstance()->cookiesSet[0]);
        $this->assertSame($headers, Testing::getInstance()->headersSet);
    }

    function testStartWithExistingSessionDoesntSetCookie() {
        $this->sess->start();
        $id = $this->sess->id();
        $this->sess->data['foo'] = 'bar';
        $this->sess->writeClose();

        Testing::reset();
        $_COOKIE['name'] = $id;
        $this->sess->start();

        $this->assertEmpty(Testing::getInstance()->cookiesSet);
    }

    /**
     * @return Session
     */
    protected function getSessionWithStorageMock() {
        $storage = $this->getMockBuilder('\\UserlandSession\\Storage\\FileStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $storage->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('name'));
        $storage->expects($this->any())
            ->method('write')
            ->will($this->returnValue(true));
        return new Session($storage);
    }

    function testGcCalled() {
        $sess = $this->getSessionWithStorageMock();
        $storage = $sess->getStorage();
        /* @var \PHPUnit_Framework_MockObject_MockObject $storage */
        $storage->expects($this->once())
            ->method('gc')
            ->with($this->sess->gc_maxlifetime)
            ->will($this->returnValue(true));

        $sess->gc_probability = 4;
        $sess->gc_divisor = 50;
        Testing::getInstance()->rand_output["1|50"] = 3;

        $sess->start();
    }

    function testGcNotCalled() {
        $sess = $this->getSessionWithStorageMock();
        $storage = $sess->getStorage();
        /* @var \PHPUnit_Framework_MockObject_MockObject $storage */
        $storage->expects($this->never())
            ->method('gc')
            ->will($this->returnValue(true));

        $sess->gc_probability = 4;
        $sess->gc_divisor = 50;
        Testing::getInstance()->rand_output["1|50"] = 25;

        $sess->start();
    }

    function testWriteClose() {
        // check saves data
        $this->assertFalse($this->root->hasChildren());
        $this->sess->start();
        $id = $this->sess->id();
        $this->sess->writeClose();
        $this->assertTrue((bool)file_get_contents($this->root->getChild("name_$id")->url()));
        $this->assertSame("", $this->sess->id());
        $this->assertNull($this->sess->data);
    }

    function testWriteCloseClosesStorage() {
        $sess = $this->getSessionWithStorageMock();
        $storage = $sess->getStorage();
        /* @var \PHPUnit_Framework_MockObject_MockObject $storage */
        $sess->start();

        $storage->expects($this->once())
            ->method('close')
            ->will($this->returnValue(true));
        $this->assertTrue($sess->writeClose());
    }

    function testId() {
        // before start
        $this->assertSame('', $this->sess->id('abcdef'));
        $this->sess->start();
        // after start
        $this->assertSame('abcdef', $this->sess->id());
        $this->assertSame('abcdef', $this->sess->id('bcdefg'));
        $this->sess->writeClose();
    }

    function testRegenerateId() {
        // before start
        $this->assertFalse($this->sess->regenerateId());

        // after
        $this->sess->start();
        $id1 = $this->sess->id();

        // populate data
        $this->sess->data['foo'] = 'bar';
        $this->sess->writeClose();

        // restart
        Testing::reset();
        $_COOKIE['name'] = $id1;
        $this->sess->start();

        Testing::getInstance()->fixedTime = 96400;
        $this->assertTrue($this->sess->regenerateId());
        $id2 = $this->sess->id();
        $this->assertNotSame($id1, $id2);

        $cookie = array (
            'name' => 'name',
            'value' => $id2,
            'expire' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
        );
        $this->assertSame($cookie, Testing::getInstance()->cookiesSet[0]);

        // by default leaves old sess storage
        $this->assertTrue((bool)$this->sess->getStorage()->read($id1));

        $this->sess->regenerateId(true);
        $id3 = $this->sess->id();
        $this->sess->writeClose();
        $this->assertNotSame($id3, $id2);

        // regen with destroy removes old storage
        $this->assertFalse((bool)$this->sess->getStorage()->read($id2));
    }

    function testDestroy() {
        // default leaves cookie
        $this->sess->getStorage()->write('abcdef', serialize(array('foo' => 'bar')));
        $_COOKIE['name'] = 'abcdef';
        $this->sess->start();
        $this->sess->destroy();
        $this->assertFalse($this->sess->getStorage()->read('abcdef'));
        $this->assertSame(array(), Testing::getInstance()->cookiesSet);

        // now test destroy with true
        //
        $this->sess->getStorage()->write('abcdef', serialize(array('foo' => 'bar')));
        $_COOKIE['name'] = 'abcdef';
        $this->sess->start();

        Testing::getInstance()->fixedTime = 96400;
        $this->sess->destroy(true);
        $this->assertFalse($this->sess->getStorage()->read('abcdef'));
        // removed cookie
        $cookie = array(
            'name' => 'name',
            'value' => '',
            'expire' => 10000,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
        );
        $this->assertSame($cookie, Testing::getInstance()->cookiesSet[0]);
    }

    function testDestructorCallsWriteClose() {
        $sess = $this->getSessionWithStorageMock();
        $storage = $sess->getStorage();
        /* @var \PHPUnit_Framework_MockObject_MockObject $storage */
        $sess->start();

        $storage->expects($this->once())
            ->method('close')
            ->will($this->returnValue(true));
        $sess = null;
    }

    function testGetSet() {
        // not started
        $this->setExpectedException('UserlandSession\\Exception');
        $this->sess->get('foo');

        $this->setExpectedException('UserlandSession\\Exception');
        $this->sess->set('foo', 'bar');

        $this->sess->start();
        $this->assertNull($this->sess->get('foo'));
        $this->assertTrue($this->sess->set('foo', 'bar'));
        $this->assertTrue($this->sess->set(array(
            'bing' => 'bing',
            'bar' => 'bar',
        )));
        $this->assertSame(array(
            'foo' => 'bar',
            'bing' => 'bing',
            'bar' => 'bar',
        ), $this->sess->data);
        $this->assertSame('bar', $this->sess->get('bar'));

        // close eliminates data
        $this->sess->writeClose();
        $this->assertNull($this->sess->data);
    }

    function testRemoveCookie() {
        $this->sess->cookie_domain = 'example.com';

        Testing::getInstance()->fixedTime = 96400;
        $this->sess->removeCookie();

        $cookie = array (
            'name' => 'name',
            'value' => '',
            'expire' => 10000,
            'path' => '/',
            'domain' => 'example.com',
            'secure' => false,
            'httponly' => false,
        );
        $this->assertSame($cookie, Testing::getInstance()->cookiesSet[0]);
    }

    function testGmtFormat() {
        $this->assertSame('Fri, 02 Jan 1970 00:00:00 GMT', Session::formatAsGmt(86400));
    }
}
