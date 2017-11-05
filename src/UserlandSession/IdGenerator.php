<?php

namespace UserlandSession;

class IdGenerator
{
    /**
     * Create a base 36 random alphanumeric string. No uppercase because these would collide with
     * lowercase chars on Windows.
     *
     * @param int $length
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     *
     * Based on Zend\Math\Rand::getString()
     *
     * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
     * @license   http://framework.zend.com/license/new-bsd New BSD License
     * @see https://github.com/zendframework/zf2/blob/master/library/Zend/Math/Rand.php#L179
     */
    public static function generateSessionId($length = 40)
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length should be >= 1');
        }

        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $numChars = 36;

        $bytes = random_bytes($length);
        $pos = 0;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $pos = ($pos + ord($bytes[$i])) % $numChars;
            $result .= $chars[$pos];
        }

        return $result;
    }
}