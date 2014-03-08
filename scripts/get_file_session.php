<?php
/**
 * This script returns a session object with storage in files in the directory
 * specified by ini_get('session.save_path').
 *
 * @return \UserlandSession\Session
 */

require_once __DIR__ . '/../autoload.php';

return call_user_func(function () {
    static $session;
    if (!$session) {
        $session = \UserlandSession\Session::factory();
    }
    return $session;
});
