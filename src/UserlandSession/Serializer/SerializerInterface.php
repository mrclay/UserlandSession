<?php

namespace UserlandSession\Serializer;

interface SerializerInterface
{
    /**
     * @param mixed $val
     * @return string
     */
    public function serialize($val);

    /**
     * @param string $string
     * @return mixed
     */
    public function unserialize($string);
}
