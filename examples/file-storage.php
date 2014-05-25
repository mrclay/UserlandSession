<?php

// store data in directory specified by ini_get('session.save_path')
$sess = (require __DIR__ . '/../scripts/get_file_session.php');
/* @var UserlandSession\Session $sess */

// // or specify the path
//
//require_once __DIR__ . '/../autoload.php';
//
//$sess = SessionBuilder::instance()->setSavePath('/tmp')->build();

$sess->start();

// increment i
$sess->set('i', $sess->get('i', 0) + 1);

header('Content-Type: text/html;charset=utf-8');
echo $sess->data['i'];