<?php

namespace UserlandSession\Serializer;

class IgbinarySerializer implements SerializerInterface
{
    /**
     * @throws \RuntimeException
     */
    public function __construct()
    {
        if (!function_exists('igbinary_serialize')) {
            throw new \RuntimeException('igbinary is not installed. See https://github.com/igbinary/igbinary#installing');
        }
    }

    /**
     * @param mixed $val
     * @return string
     */
    public function serialize($val)
    {
        return igbinary_serialize($val);
    }

    /**
     * @param string $string
     * @return mixed
     */
    public function unserialize($string)
    {
        return igbinary_unserialize($string);
    }
}
