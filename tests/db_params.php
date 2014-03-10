<?php

if (is_file(__DIR__ . '/db_params.local.php')) {
    return (require __DIR__ . '/db_params.local.php');
}

return array(
    'dsn' => 'mysql:host=localhost;dbname=ulsess;charset=UTF8',
    'driver' => 'mysql',
    'username' => 'travis',
    'password' => '',
    'host' => 'localhost',
    'dbname' => 'ulsess',
    'port' => 3306,
    'table' => 'userland_sessions',
);