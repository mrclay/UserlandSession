<?php

namespace UserlandSession\Handler;

/**
 * PDO session storage.
 *
 * This uses the schema described in schema/mysql.sql.
 *
 * A separate table is required for each storage object you use because the garbage collector does not care
 * about the session name.
 */
class PdoHandler implements \SessionHandlerInterface
{
    /**
     * @param array $options Storage container options
     *
     *   'table' : name of the session data table (required)
     *
     *   'pdo' : a PDO connection. If not given, the following options can be given and
     *           PdoHandler will make a new PDO connection when needed:
     *
     *   'dsn' : argument for PDO::__construct
     *   'username' : argument for PDO::__construct
     *   'password' : argument for PDO::__construct
     *   'driver_options' : argument for PDO::__construct
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $options)
    {
        $this->options = array_merge(
            array(
                'dsn' => null,
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
                throw new \InvalidArgumentException('The option "pdo" must be a PDO object, if given.');
            }
            $this->pdo = $this->options['pdo'];
            unset($this->options['pdo']);
        } else {
            if ($this->options['dsn'] === null
                    || $this->options['username'] === null
                    || $this->options['password'] === null) {
                throw new \InvalidArgumentException('If PDO not given, you must give dsn, username, and password.');
            }
        }
    }

    /**
     * @param string $save_path
     * @param string $name
     *
     * @return bool
     */
    public function open($save_path, $name)
    {
        $this->getPdo();
        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        return true;
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
        $this->pdo->exec($sql);
        return true;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->options['table'];
    }

    /**
     * @return \PDO
     */
    public function getPdo()
    {
        $o = $this->options;
        if (!$this->pdo) {
            $this->pdo = new \PDO($o['dsn'], $o['username'], $o['password'], $o['driver_options']);
        }
        return $this->pdo;
    }

    /**
     * @var array
     */
    protected $options;

    /**
     * @var \PDO
     */
    protected $pdo;
}