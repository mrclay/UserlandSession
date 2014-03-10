<?php
/**
 * This script returns a session object with storage in files in the directory
 * specified by ini_get('session.save_path').
 *
 * @return \UserlandSession\Session
 */

use UserlandSession\Handler\FileHandler;
use UserlandSession\Session;
use UserlandSession\SessionBuilder;

require_once __DIR__ . '/../autoload.php';

return call_user_func(function () {
    static $session;
    if (!$session) {
        $builder = SessionBuilder::instance();
        $save_path = session_save_path();
        if ($save_path) {
            $builder->setSavePath($save_path);
        } else {
            $builder->useSystemTmp();
        }
        $session = $builder->build();
    }
    return $session;
});
