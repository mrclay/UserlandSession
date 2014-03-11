<?php

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/override_builtins.php';

// create DB table
call_user_func(function () {
    $p = (require __DIR__ . '/db_params.php');
    $pdo = new PDO($p['dsn'], $p['username'], $p['password']);
    $query = file_get_contents(__DIR__ . '/../schema/mysql.sql');
    $pdo->exec($query);
});