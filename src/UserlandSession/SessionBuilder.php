<?php

namespace UserlandSession;

use UserlandSession\Handler\FileHandler;
use UserlandSession\Handler\PdoHandler;
use UserlandSession\Serializer\SerializerInterface;

/**
 * Fluent API for creating sessions
 */
class SessionBuilder
{
    protected $name = Session::DEFAULT_SESSION_NAME;
    protected $savePath;
    protected $handler;
    protected $serializer;
    protected $locking = true;
    protected $table;
    protected $pdo;
    protected $dbCredentials = array();
    protected $props = array();

    public function __construct()
    {
        $this->savePath = session_save_path();
    }

    /**
     * @return $this
     */
    public static function instance()
    {
        return new self();
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function setSavePath($path)
    {
        $this->savePath = $path;
        return $this;
    }

    /**
     * @return $this
     */
    public function useSystemTmp()
    {
        return $this->setSavePath(sys_get_temp_dir());
    }

    /**
     * @param bool $locking
     *
     * @return $this
     */
    public function setFileLocking($locking = true)
    {
        $this->locking = $locking;
        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string $table
     *
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @param \PDO $pdo
     *
     * @return $this
     */
    public function setPdo(\PDO $pdo)
    {
        $this->pdo = $pdo;
        return $this;
    }

    /**
     * @param string[] $creds
     *
     * @return $this
     */
    public function setDbCredentials(array $creds)
    {
        $this->dbCredentials = $creds;
        return $this;
    }

    /**
     * @param \SessionHandlerInterface $handler
     *
     * @return $this
     */
    public function setHandler(\SessionHandlerInterface $handler)
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * @param SerializerInterface $serializer
     *
     * @return $this
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
        return $this;
    }

    /**
     * Create a new session with the appropriate storage handler
     *
     * @return Session
     */
    public function build()
    {
        if ($this->handler) {
            $handler = $this->handler;
        } elseif ($this->pdo || $this->dbCredentials) {
            $options = $this->dbCredentials;
            $options['table'] = $this->table;
            if ($this->pdo) {
                $options['pdo'] = $this->pdo;
            }
            $handler = new PdoHandler($options);
        } else {
            $handler = new FileHandler($this->locking);
        }
        return new Session($handler, $this->name, $this->savePath, $this->serializer);
    }
}