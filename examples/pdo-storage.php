<?php

use UserlandSession\Session;
use UserlandSession\Storage\PdoStorage;

require __DIR__ . '/../autoload.php';

// you can have the storage class open the PDO connection...
$params = (require __DIR__ . '/../tests/db_params.php');
$storage = new PdoStorage('ULSESS', array(
    'table' => $params['table'],
    'dsn' => "{$params['driver']}:host={$params['host']};dbname={$params['dbname']};charset=UTF8",
    'username' => $params['username'],
    'password' => $params['password'],
));

// // ...or you can pass it in.
//
//$storage = new \UserlandSession\Storage\PdoStorage('ULSESS', array(
//	'table' => 'userland_sessions',
//	'pdo' => $pdo,
//));
//

$sess = new Session($storage);

$sess->start();

// increment i
$sess->set('i', $sess->get('i', 0) + 1);

// test valid/invalid strings. http://www.php.net/manual/en/reference.pcre.pattern.modifiers.php#54805
$examples = array(
    'Valid ASCII' => "a",
    'Valid 2 Octet Sequence' => "\xc3\xb1",
    'Invalid 2 Octet Sequence' => "\xc3\x28",
    'Invalid Sequence Identifier' => "\xa0\xa1",
    'Valid 3 Octet Sequence' => "\xe2\x82\xa1",
    'Invalid 3 Octet Sequence (in 2nd Octet)' => "\xe2\x28\xa1",
    'Invalid 3 Octet Sequence (in 3rd Octet)' => "\xe2\x82\x28",
    'Valid 4 Octet Sequence' => "\xf0\x90\x8c\xbc",
    'Invalid 4 Octet Sequence (in 2nd Octet)' => "\xf0\x28\x8c\xbc",
    'Invalid 4 Octet Sequence (in 3rd Octet)' => "\xf0\x90\x28\xbc",
    'Invalid 4 Octet Sequence (in 4th Octet)' => "\xf0\x28\x8c\x28",
    'Valid 5 Octet Sequence (but not Unicode!)' => "\xf8\xa1\xa1\xa1\xa1",
    'Valid 6 Octet Sequence (but not Unicode!)' => "\xfc\xa1\xa1\xa1\xa1\xa1",
);

$report = $sess->data['i'];

if (!isset($sess->data['examples'])) {
    $sess->data['examples'] = $examples;
} else {
    $report .= "<br>";
    if ($sess->data['examples'] === $examples) {
        $report .= "Binary data stored successfully.";
    } else {
        $report .= "Binary data was altered in session.";
    }
}

header('Content-Type: text/html;charset=utf-8');
echo $report;