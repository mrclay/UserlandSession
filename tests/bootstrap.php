<?php

namespace {
    require_once __DIR__ . '/../autoload.php';
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * override some global functions in these namespaces
 */
namespace UserlandSession {

    function setcookie($name, $value, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false)
    {
        Testing::getInstance()->cookiesSet[] = array(
            'name' => $name,
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
        );
        return !headers_sent();
    }

    function header($string, $replace = null, $http_response_code = null)
    {
        Testing::getInstance()->headersSet[] = $string;
    }

    function headers_sent(&$file = null, &$line = null)
    {
        $testing = Testing::getInstance();

        $return = $testing->headers_sent;
        if ($return) {
            $file = $testing->headers_sent_file;
            $line = $testing->headers_sent_line;
        }
        return $return;
    }

    function time()
    {
        $testing = Testing::getInstance();

        if ($testing->fixedTime) {
            return $testing->fixedTime;
        }
        return \time() + $testing->timeOffset;
    }

    class Testing
    {
        public $timeOffset = 0;
        public $fixedTime = 0;

        public $cookiesSet = array();

        public $headersSet = array();

        public $headers_sent = false;
        public $headers_sent_file;
        public $headers_sent_line;

        protected static $instance;

        public static function getInstance() {
            if (!self::$instance) {
                self::$instance = new Testing();
            }
            return self::$instance;
        }

        public static function reset() {
            self::$instance = null;
        }
    }
}

namespace UserlandSession\Storage {
    function time()
    {
        return \UserlandSession\time();
    }
}
