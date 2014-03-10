<?php

if (interface_exists('SessionHandlerInterface', false)) {
    return;
}

/**
 * @link http://php.net/manual/en/class.sessionhandlerinterface.php
 */
interface SessionHandlerInterface {

    /**
     * @link http://php.net/manual/en/sessionhandlerinterafce.close.php
     * @return bool
     */
    public function close();

    /**
     * @link http://php.net/manual/en/sessionhandlerinterafce.destroy.php
     * @param int $session_id The session ID being destroyed.
     * @return bool
     */
    public function destroy($session_id);

    /**
     * @link http://php.net/manual/en/sessionhandlerinterafce.gc.php
     * @param int $maxlifetime
     * @return bool
     */
    public function gc($maxlifetime);

    /**
     * @link http://php.net/manual/en/sessionhandlerinterafce.open.php
     * @param string $save_path The path where to store/retrieve the session.
     * @param string $name The session name.
     * @return bool
     */
    public function open($save_path, $name);

    /**
     * @link http://php.net/manual/en/sessionhandlerinterafce.read.php
     * @param string $session_id The session id to read data for.
     * @return string
     */
    public function read($session_id);

    /**
     * @link http://php.net/manual/en/sessionhandlerinterafce.write.php
     * @param string $session_id The session id.
     * @param string $session_data
     * @return bool
     */
    public function write($session_id, $session_data);
}