<?php

namespace UserlandSession;

/**
 * Simple wrapper for PHP global state functions (untestables)
 */
class Http
{
    /**
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @return bool
     */
    public function setcookie($name, $value, $expire = 0, $path, $domain, $secure = false, $httponly = false)
    {
        return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * @param string $string
     * @param bool $replace
     * @param int $http_response_code
     */
    public function header($string, $replace = null, $http_response_code = null)
    {
        header($string, $replace, $http_response_code);
    }

    /**
     * @param string $file
     * @param string $line
     * @return bool
     */
    public function headers_sent(&$file = null, &$line = null)
    {
        return headers_sent($file, $line);
    }
}
