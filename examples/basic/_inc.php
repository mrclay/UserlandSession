<?php

require dirname(__DIR__) . '/autoload.php';

function getStateManager() {
    $storage = new Shibalike\Util\UserlandSession\Storage\Files('SHIBALIKE_BASIC');
    $session = Shibalike\Util\UserlandSession::factory($storage);
    return new Shibalike\StateManager\UserlandSession($session);
}

// normally a DB
function getAttrStore() {
    return new Shibalike\Attr\Store\ArrayStore(array(
        'jadmin' => array(
            'uid' => 1111,
            'displayname' => 'Johnny Admin',
        ),
        'juser' => array(
            'uid' => 2222,
            'displayname' => 'Jane User',
        ),
    ));
}

function getConfig() {
    $config = new Shibalike\Config();
    $config->idpUrl = 'idp.php';
    return $config;
}