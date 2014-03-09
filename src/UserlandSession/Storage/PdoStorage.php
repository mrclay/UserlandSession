<?php

namespace UserlandSession\Storage;

use UserlandSession\Session;

/**
 * PDO session storage.
 *
 * This uses the schema described in utils/pdo_schema.sql.
 *
 * A separate table is required for each storage object you use because the garbage collector does not care
 * about the session name.
 */
class PdoStorage implements StorageInterface
{
    /**
     * @param string $name session name (to be used in cookie)
     * @param array $options for the storage container
     *
     *   'table' : name of the session data table (required)
     *
     *   'pdo' : a PDO connection. If not given, the following options can be given and
     *           PdoStorage will make a new PDO connection when needed:
     *
     *   'dsn' : argument for PDO::__construct
     *   'username' : argument for PDO::__construct
     *   'password' : argument for PDO::__construct
     *   'driver_options' : argument for PDO::__construct
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($name = Session::DEFAULT_SESSION_NAME, array $options = array())
    {
        $this->name = $name;
        $this->options = array_merge(
            array(
                'username' => null,
                'password' => null,
                'driver_options' => null,
                'table' => null,
                'pdo' => null,
            ),
            $options
        );

        if (!$this->options['table']) {
            throw new \InvalidArgumentException('The option "table" must be specified.');
        }
        if ($this->options['pdo']) {
            if (!$this->options['pdo'] instanceof \PDO) {
                throw new \InvalidArgumentException('The option "pdo" must be a PDO object if given.');
            }
            $this->pdo = $this->options['pdo'];
            unset($this->options['pdo']);
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function open()
    {
        $o = $this->options;
        if (!$this->pdo) {
            $this->pdo = new \PDO($o['dsn'], $o['username'], $o['password'], $o['driver_options']);
        }
        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {

    }

    /**
     * @param string $id
     * @return string|false
     */
    public function read($id)
    {
        $sql = "
            SELECT `data` 
            FROM `{$this->options['table']}`
            WHERE `id` = " . $this->pdo->quote($id) . "
        ";
        $stmt = $this->pdo->query($sql);
        if ($stmt) {
            foreach ($this->pdo->query($sql) as $row) {
                return $row['data'];
            }
        }
        return false;
    }

    /**
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function write($id, $data)
    {
        $sql = "
            REPLACE INTO `{$this->options['table']}`
            VALUES (
                " . $this->pdo->quote($id) . ",
                " . $this->pdo->quote($data) . ",
                '" . time() . "')
        ";
        return (bool)$this->pdo->exec($sql);

    }

    /**
     * @param string $id
     * @return bool
     */
    public function destroy($id)
    {
        $sql = "
            DELETE FROM `{$this->options['table']}`
            WHERE `id` = " . $this->pdo->quote($id) . "
        ";
        return (bool)$this->pdo->exec($sql);
    }

    /**
     * @param int $maxLifetime
     * @return bool
     */
    public function gc($maxLifetime)
    {
        $sql = "
            DELETE FROM `{$this->options['table']}`
            WHERE `time` < " . (int)(time() - $maxLifetime) . "
        ";
        return (bool)$this->pdo->exec($sql);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function idIsValid($id)
    {
        return preg_match('/^[a-zA-Z0-9\\-\\_]+$/', $id);
    }

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var \PDO
     */
    protected $pdo;
}