<?php

namespace UserlandSession\Tests;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use UserlandSession\Session;
use UserlandSession\Handler\FileHandler;
use UserlandSession\BuiltIns;

class SessionTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var Session
     */
    protected $sess;

    /**
     * @var FileHandler
     */
    protected $handler;

    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    function setUp() {
        BuiltIns::reset();
        $this->root = vfsStream::setup();
        $this->handler = new FileHandler(false);
        $this->sess = new Session($this->handler, 'name', $this->root->url());
    }

    function tearDown() {
        // necessary to avoid warnings due to vfsStream
        $this->sess = null;
        BuiltIns::reset();
    }

    function testNotStarted() {
        $this->assertSame('', $this->sess->id());
        $this->assertFalse($this->sess->writeClose());
        $this->assertFalse($this->sess->destroy());
        $this->assertFalse($this->sess->regenerateId());
    }

    function testCantStart() {
        BuiltIns::getInstance()->headers_sent = true;
        $this->assertFalse($this->sess->start());
    }

    function testSuccessfulStartReturnsTrue() {
        $this->assertTrue($this->sess->start());
        $this->assertSame(array(), $this->sess->data);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testInvalidName() {
        new Session($this->handler, '...');
    }

    function testGetHandler() {
        $this->assertSame($this->handler, $this->sess->getHandler());
    }

    function testFixationAttack() {
        $_COOKIE['name'] = 'abc123';
        $this->sess->start();
        $this->assertNotSame('abc123', $this->sess->id());
    }

    function testSessionSniffing() {
        $this->handler->open($this->root->url(), 'name');
        $this->handler->write('abcde', 'notSerialization');
        $this->handler->write('12345', serialize(array('foo' => 'bar')));

        $cookieValues = array(
            // cookie value        sniffed ID  likely exists  data
            array(null,            false,      false,         null),
            array(array('1', '2'), false,      false,         null),
            array('..',            false,      false,         null),
            array('y78fy',         'y78fy',    false,         null),
            array('abcde',         'abcde',    true,          array()),
            array('12345',         '12345',    true,          array('foo' => 'bar')),
        );

        foreach ($cookieValues as $i => $arr) {
            list($cookieId, $id, $likely, $data) = $arr;

            unset($_COOKIE['name']);
            if ($cookieId) {
                $_COOKIE['name'] = $cookieId;
            }

            $this->assertSame($id, $this->sess->getIdFromCookie(), "fail iteration $i");
            $this->assertSame($likely, $this->sess->sessionLikelyExists(), "fail iteration $i");

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

                $this->sess->writeClose();

                $this->assertTrue((bool)file_get_contents($this->root->getChild("name_$sessId")->url()));
            }
        }
    }

    function testStartHeadersNoCache() {
        // no cache is default
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
        $this->assertSame($cookie, BuiltIns::getInstance()->cookiesSet[0]);
        $this->assertSame($headers, BuiltIns::getInstance()->headersSet);
    }

    function testStartHeadersWithPublicCache() {
        BuiltIns::getInstance()->fixedTime = 96400;
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
        $this->assertSame($cookie, BuiltIns::getInstance()->cookiesSet[0]);
        $this->assertSame($headers, BuiltIns::getInstance()->headersSet);

    }

    function testStartHeaderPrivateNoExpire() {
        BuiltIns::getInstance()->fixedTime = 96400;
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
        $this->assertSame($cookie, BuiltIns::getInstance()->cookiesSet[0]);
        $this->assertSame($headers, BuiltIns::getInstance()->headersSet);
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
        $this->assertSame($cookie, BuiltIns::getInstance()->cookiesSet[0]);
        $this->assertSame($headers, BuiltIns::getInstance()->headersSet);
    }

    function testStartHeaderNoCacheLimiting() {
        $this->sess->cache_limiter = Session::CACHE_LIMITER_NONE;

        $this->sess->cookie_lifetime = 400;
        BuiltIns::getInstance()->fixedTime = 86400;

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
        $this->assertSame($cookie, BuiltIns::getInstance()->cookiesSet[0]);
        $this->assertSame($headers, BuiltIns::getInstance()->headersSet);
    }

    function testStartWithExistingSessionDoesntSetCookie() {
        $this->sess->start();
        $id = $this->sess->id();
        $this->sess->data['foo'] = 'bar';
        $this->sess->writeClose();

        BuiltIns::reset();
        $_COOKIE['name'] = $id;
        $this->sess->start();

        $this->assertEmpty(BuiltIns::getInstance()->cookiesSet);
    }

    /**
     * @return Session
     */
    protected function getSessionWithHandlerMock() {
        $handler = $this->getMockBuilder('\\UserlandSession\\Handler\\FileHandler')
            ->disableOriginalConstructor()
            ->getMock();
        $handler->expects($this->any())
            ->method('write')
            ->will($this->returnValue(true));
        /** @noinspection PhpParamsInspection */
        return new Session($handler, 'name', $this->root->url());
    }

    function testStartOpensHandler() {
        $sess = $this->getSessionWithHandlerMock();
        $storage = $sess->getHandler();
        /* @var \PHPUnit_Framework_MockObject_MockObject $storage */

        $storage->expects($this->once())
            ->method('open')
            ->with($this->root->url(), 'name')
            ->will($this->returnValue(true));
        $sess->start();
    }

    function testStartWithGc() {
        $sess = $this->getSessionWithHandlerMock();
        $storage = $sess->getHandler();
        /* @var \PHPUnit_Framework_MockObject_MockObject $storage */
        $storage->expects($this->once())
            ->method('gc')
            ->with($this->sess->gc_maxlifetime)
            ->will($this->returnValue(true));

        $sess->gc_probability = 4;
        $sess->gc_divisor = 50;
        BuiltIns::getInstance()->rand_output["1|50"] = 3;

        $sess->start();
    }

    function testStartWithoutGc() {
        $sess = $this->getSessionWithHandlerMock();
        $storage = $sess->getHandler();
        /* @var \PHPUnit_Framework_MockObject_MockObject $storage */
        $storage->expects($this->never())
            ->method('gc')
            ->will($this->returnValue(true));

        $sess->gc_probability = 4;
        $sess->gc_divisor = 50;
        BuiltIns::getInstance()->rand_output["1|50"] = 25;

        $sess->start();
    }

    /**
     * @expectedException \UserlandSession\Exception
     */
    function testStartComplainsIfDataPresent() {
        $this->sess->data = array('foo' => 1);
        $this->sess->start();
    }

    function testWriteClose() {
        // check saves data
        $this->sess->start();
        $id = $this->sess->id();
        $this->sess->writeClose();
        $this->assertTrue((bool)file_get_contents($this->root->getChild("name_$id")->url()));
        $this->assertSame("", $this->sess->id());
        $this->assertNull($this->sess->data);
    }

    function testWriteCloseClosesStorage() {
        $sess = $this->getSessionWithHandlerMock();
        $storage = $sess->getHandler();
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

        $this->setExpectedException('UserlandSession\\Exception');
        $this->sess->id('bcdefg');
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
        BuiltIns::reset();
        $_COOKIE['name'] = $id1;
        $this->sess->start();

        BuiltIns::getInstance()->fixedTime = 96400;
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
        $this->assertSame($cookie, BuiltIns::getInstance()->cookiesSet[0]);

        // by default leaves old sess storage
        $this->assertTrue((bool)$this->sess->getHandler()->read($id1));

        $this->sess->regenerateId(true);
        $id3 = $this->sess->id();
        $this->sess->writeClose();
        $this->assertNotSame($id3, $id2);

        // regen with destroy removes old storage
        $this->assertFalse((bool)$this->sess->getHandler()->read($id2));
    }

    function testDestroy() {
        // default leaves cookie

        // prepopulate session
        file_put_contents($this->root->url() . '/name_abcdef', serialize(array('foo' => 'bar')));
        $_COOKIE['name'] = 'abcdef';
        $this->sess->start();
        $this->sess->destroy();
        $this->assertNull($this->sess->data);
        $this->assertFalse($this->sess->getHandler()->read('abcdef'));
        $this->assertSame(array(), BuiltIns::getInstance()->cookiesSet);

        // now test destroy with true
        //
        $this->sess->getHandler()->write('abcdef', serialize(array('foo' => 'bar')));
        $_COOKIE['name'] = 'abcdef';
        $this->sess->start();

        BuiltIns::getInstance()->fixedTime = 96400;
        $this->sess->destroy(true);
        $this->assertFalse($this->sess->getHandler()->read('abcdef'));
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
        $this->assertSame($cookie, BuiltIns::getInstance()->cookiesSet[0]);
    }

    function testDestructorCallsWriteClose() {
        $sess = $this->getSessionWithHandlerMock();
        $storage = $sess->getHandler();
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

        BuiltIns::getInstance()->fixedTime = 96400;
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
        $this->assertSame($cookie, BuiltIns::getInstance()->cookiesSet[0]);
    }

    function testGmtFormat() {
        $this->assertSame('Fri, 02 Jan 1970 00:00:00 GMT', Session::formatAsGmt(86400));
    }

    function testSerializer() {
        $serializer = $this->getMock('\UserlandSession\Serializer\PhpSerializer');
        $serializer->expects($this->once())
            ->method('serialize')
            ->with(array('foo' => 1))
            ->will($this->returnValue('foo=1'));

        $sess = new Session($this->handler, 'name', $this->root->url(), $serializer);
        $sess->start();
        $sess->data['foo'] = 1;
        $id = $sess->id();
        $sess->writeClose();

        // make sure GC doesn't run
        $sess->gc_probability = 4;
        $sess->gc_divisor = 50;
        BuiltIns::getInstance()->rand_output["1|50"] = 25;

        $serializer = $this->getMock('\UserlandSession\Serializer\PhpSerializer');
        $serializer->expects($this->once())
            ->method('unserialize')
            ->with('foo=1')
            ->will($this->returnValue(array('foo' => 1)));

        $_COOKIE['name'] = $id;
        $sess = new Session($this->handler, 'name', $this->root->url(), $serializer);
        $sess->start();

        $this->assertEquals(array('foo' => 1), $sess->data);
    }
}
