<?php

namespace UserlandSession\Tests;

use UserlandSession\Handler\FileHandler;
use UserlandSession\Handler\PdoHandler;
use UserlandSession\Serializer\PhpSerializer;
use UserlandSession\Session;
use UserlandSession\SessionBuilder;

class SessionBuilderTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var SessionBuilder
     */
    protected $builder;

    function setUp() {
        $this->builder = new SessionBuilder();
    }

    function tearDown() {

    }

    function testConstructor() {
        session_save_path('/tmp_not_real');
        $session = SessionBuilder::instance()->instance()->build();
        $this->assertSame('/tmp_not_real', $session->getSavePath());
    }

    function testSetSavePath() {
        $path = '/tmp_not_real';
        $this->assertSame($path, $this->builder->setSavePath($path)->build()->getSavePath());
    }

    function testUseSystemTmp() {
        $path = sys_get_temp_dir();
        $this->assertSame($path, $this->builder->useSystemTmp()->build()->getSavePath());
    }

    function testSetFileLocking() {
        $handler = $this->builder->build()->getHandler();
        /* @var FileHandler $handler */
        $this->assertTrue($handler->getLocking());

        $handler = $this->builder->setFileLocking(false)->build()->getHandler();
        /* @var FileHandler $handler */
        $this->assertFalse($handler->getLocking());
    }

    function testSetName() {
        $this->assertSame(Session::DEFAULT_SESSION_NAME, $this->builder->build()->getName());
        $this->assertSame('foo', $this->builder->setName('foo')->build()->getName());
    }

    function testSetTable() {
        $fakeCreds = array(
            'dsn' => 'blah:',
            'username' => 'user',
            'password' => '',
        );
        $handler = $this->builder->setTable('foo')->setDbCredentials($fakeCreds)->build()->getHandler();
        /* @var PdoHandler $handler */
        $this->assertSame('foo', $handler->getTable());
    }

    function testSetPdo() {
        $p = (require __DIR__ . '/../../db_params.php');
        $pdo = new \PDO($p['dsn'], $p['username'], $p['password']);

        $handler = $this->builder->setPdo($pdo)->setTable('foo')->build()->getHandler();
        /* @var PdoHandler $handler */

        $this->assertSame($pdo, $handler->getPdo());
    }

    function testSetDbCredentials() {
        $creds = (require __DIR__ . '/../../db_params.php');

        $handler = $this->builder->setDbCredentials($creds)->setTable('foo')->build()->getHandler();
        /* @var PdoHandler $handler */
        $pdo = $handler->getPdo();

        $this->assertSame($creds['driver'], $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    function testSetHandler() {
        $handler = new FileHandler();
        $sess = $this->builder->setHandler($handler)->build();
        $this->assertSame($handler, $sess->getHandler());
    }

    function testSetSerializer() {
        $serializer = $this->getMock('UserlandSession\Serializer\PhpSerializer');
        $serializer->expects($this->once())
            ->method('serialize');
        $sess = $this->builder->setSerializer($serializer)->build();
        $sess->start();
        $sess->data['foo'] = 1;
        $sess->writeClose();
    }

    function testHandlerOverridesPdo() {
        $handler = $this->getMockBuilder('UserlandSession\\Handler\\FileHandler')
            ->disableOriginalConstructor()
            ->getMock();

        $p = (require __DIR__ . '/../../db_params.php');
        $pdo = new \PDO($p['dsn'], $p['username'], $p['password']);

        /** @noinspection PhpParamsInspection */
        $sess = $this->builder
            ->setPdo($pdo)
            ->setTable('foo')
            ->setHandler($handler)
            ->build();

        $this->assertSame($handler, $sess->getHandler());
    }

    function testPdoSettingsOverrideFile() {
        $p = (require __DIR__ . '/../../db_params.php');

        $this->assertInstanceOf(
            'UserlandSession\\Handler\\PdoHandler',
            $this->builder
                ->setDbCredentials($p)
                ->setTable('foo')
                ->setFileLocking(false)
                ->build()->getHandler()
        );

        $pdo = new \PDO($p['dsn'], $p['username'], $p['password']);
        $this->assertInstanceOf(
            'UserlandSession\\Handler\\PdoHandler',
            $this->builder
                ->setPdo($pdo)
                ->setTable('foo')
                ->setFileLocking(false)
                ->build()->getHandler()
        );
    }

    function testDefaultIsFileHandler() {
        $this->assertInstanceOf(
            'UserlandSession\\Handler\\FileHandler',
            $this->builder->build()->getHandler()
        );
    }
}
