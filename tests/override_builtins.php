<?php
/**
 * Here we override some built-in functions within the UserlandSession and
 * UserlandSession\Handler namespaces, using the BuiltIns class to capture
 * arguments and control behavior.
 */

namespace UserlandSession {

    function setcookie($name, $value, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false)
    {
        BuiltIns::getInstance()->cookiesSet[] = array(
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
        BuiltIns::getInstance()->headersSet[] = $string;
    }

    function headers_sent(&$file = null, &$line = null)
    {
        $testing = BuiltIns::getInstance();

        $return = $testing->headers_sent;
        if ($return) {
            $file = $testing->headers_sent_file;
            $line = $testing->headers_sent_line;
        }
        return $return;
    }

    function time()
    {
        $testing = BuiltIns::getInstance();

        if ($testing->fixedTime) {
            return $testing->fixedTime;
        }
        return \time() + $testing->timeOffset;
    }

    function mt_rand($min = 0, $max = null) {
        if ($max === null) {
            $max = mt_getrandmax();
        }
        return BuiltIns::getInstance()->mt_rand($min, $max);
    }
}

namespace UserlandSession\Handler {
    function time()
    {
        return \UserlandSession\time();
    }
}
