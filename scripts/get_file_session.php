<?php
/**
 * This script returns a session object with storage in files in the directory
 * specified by session_save_path().
 *
 * @return \UserlandSession\Session
 */

use UserlandSession\SessionBuilder;

require_once __DIR__ . '/../autoload.php';

return call_user_func(function () {
    static $session;
    if (!$session) {
        $session = SessionBuilder::instance()->build();
    }
    return $session;
});
