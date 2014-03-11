<?php

use UserlandSession\SessionBuilder;

require __DIR__ . '/../autoload.php';

// you can have the storage class open the PDO connection...
$params = (require __DIR__ . '/../tests/db_params.php');

$sess = SessionBuilder::instance()
    ->setDbCredentials($params)
    ->setTable($params['table'])
    ->build();

// // ...or you can pass it in.

//$sess = SessionBuilder::instance()
//    ->setPdo($pdo)
//    ->setTable('userland_sessions')
//    ->build();

$sess->start();

// increment i
$sess->set('i', $sess->get('i', 0) + 1);

header('Content-Type: text/html;charset=utf-8');
echo $sess->data['i'];