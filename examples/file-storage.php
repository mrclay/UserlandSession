<?php

use UserlandSession\Session;
use UserlandSession\Handler\FileHandler;

// store data in directory specified by ini_get('session.save_path')
$sess = (require __DIR__ . '/../scripts/get_file_session.php');
/* @var Session $sess */

// // or specify the path
//
//require_once __DIR__ . '/../autoload.php';
//$storage = new FileHandler();
//$sess = new Session($storage, Session::DEFAULT_SESSION_NAME, '/tmp');

$sess->start();

// increment i
$sess->set('i', $sess->get('i', 0) + 1);

header('Content-Type: text/html;charset=utf-8');
echo $sess->data['i'];