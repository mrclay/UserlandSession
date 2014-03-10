<?php
/**
 * This script returns a session object with storage in files in the directory
 * specified by ini_get('session.save_path').
 *
 * @return \UserlandSession\Session
 */

use UserlandSession\Handler\FileHandler;
use UserlandSession\Session;

require_once __DIR__ . '/../autoload.php';

return call_user_func(function () {
    static $session;
    if (!$session) {
        $save_path = session_save_path();
        if (!$save_path) {
            $save_path = sys_get_temp_dir();
        }
        $handler = new FileHandler();
        $session = new Session($handler, Session::DEFAULT_SESSION_NAME, $save_path);
    }
    return $session;
});
