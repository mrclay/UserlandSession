<?php

namespace UserlandSession\Storage;

use UserlandSession\Session;

interface StorageInterface
{
    /**
     * @param string $name session name (to be used in cookie)
     * @param array $options for the storage container
     */
    public function __construct($name = Session::DEFAULT_SESSION_NAME, array $options = array());

    /**
     * @return string
     */
    public function getName();

    /**
     * @return bool
     */
    public function open();

    /**
     * @return bool
     */
    public function close();

    /**
     * @param string $id
     * @return bool
     */
    public function read($id);

    /**
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function write($id, $data);

    /**
     * @param string $id
     * @return bool
     */
    public function destroy($id);

    /**
     * @param int $maxLifetime
     * @return bool
     */
    public function gc($maxLifetime);

    /**
     * @param string $id
     * @return bool
     */
    public function idIsValid($id);
}