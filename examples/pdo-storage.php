<?php

use UserlandSession\Session;
use UserlandSession\Handler\PdoHandler;

require __DIR__ . '/../autoload.php';

// you can have the storage class open the PDO connection...
$params = (require __DIR__ . '/../tests/db_params.php');
$storage = new PdoHandler(array(
    'table' => $params['table'],
    'dsn' => "{$params['driver']}:host={$params['host']};dbname={$params['dbname']};charset=UTF8",
    'username' => $params['username'],
    'password' => $params['password'],
));

// // ...or you can pass it in.
//
//$storage = new \UserlandSession\Storage\PdoHandler(array(
//	'table' => 'userland_sessions',
//	'pdo' => $pdo,
//));
//

$sess = new Session($storage);

$sess->start();

// increment i
$sess->set('i', $sess->get('i', 0) + 1);

header('Content-Type: text/html;charset=utf-8');
echo $sess->data['i'];