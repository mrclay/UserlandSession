<?php
/**
 * Here we override some built-in functions within the UserlandSession and
 * UserlandSession\Handler namespaces, using the BuiltIns class to capture
 * arguments and control behavior.
 */

namespace UserlandSession {

    /**
     * Captures arguments in BuiltIns::cookiesSet. No side-effects
     */
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
        // note: intentional use of local headers_sent()
        return !headers_sent();
    }

    /**
     * Captures the string argument in BuiltIns::headersSet. No side-effects
     */
    function header($string)
    {
        BuiltIns::getInstance()->headersSet[] = $string;
    }

    /**
     * Controlled by BuiltIns::headers_sent(_file|_line)
     */
    function headers_sent(&$file = null, &$line = null)
    {
        $builtIns = BuiltIns::getInstance();

        $return = $builtIns->headers_sent;
        if ($return) {
            $file = $builtIns->headers_sent_file;
            $line = $builtIns->headers_sent_line;
        }
        return $return;
    }

    /**
     * Controlled by BuiltIns::(fixedTime|timeOffset)
     */
    function time()
    {
        $builtIns = BuiltIns::getInstance();

        if ($builtIns->fixedTime) {
            return $builtIns->fixedTime;
        }
        return \time() + $builtIns->timeOffset;
    }

    /**
     * Controlled by BuiltIns::rand_output
     */
    function mt_rand($min = 0, $max = null) {
        if ($max === null) {
            $max = mt_getrandmax();
        }
        return BuiltIns::getInstance()->mt_rand($min, $max);
    }
}

namespace UserlandSession\Handler {
    /**
     * Controlled by BuiltIns::(fixedTime|timeOffset)
     */
    function time()
    {
        return \UserlandSession\time();
    }
}
