<?php

require dirname(__DIR__) . '/autoload.php';

function getStateManager() {
    $storage = new Shibalike\Util\UserlandSession\Storage\Files('SHIBALIKE_SHIBIDP');
    $session = Shibalike\Util\UserlandSession::factory($storage);
    return new Shibalike\StateManager\UserlandSession($session);
}

// get attributes from Shibboleth!
function getAttrStore() {
    $source = array();
    foreach (array(
        'businessName', 
        'UFADGroupsDN', 
        'cn', 
        'eduperson_affiliations', 
        'eppn', 
        'givenName', 
        'glid', 
        'loa', 
        'mail', 
        'middleName',
        'postalAddress',
        'sn',
        'ufid',
        'uf_affiliations',
        'primary-affiliation') as $key) 
    {
        $source[$_SERVER['glid']][$key] = $_SERVER[$key];
    }
    return new Shibalike\Attr\Store\ArrayStore($source);
}

function getUrlConfig() {
    $config = new Shibalike\UrlConfig();
    $config->idpUrl = './idp/';
    $config->postLogoutUrl = '../goodbye.php';
    $config->spUrl = './';
    return $config;
}