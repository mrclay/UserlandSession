<?php

namespace UserlandSession\Serializer;

class PhpSerializer implements SerializerInterface
{
    /**
     * @param mixed $val
     * @return string
     * @see serialize()
     */
    public function serialize($val)
    {
        return serialize($val);
    }

    /**
     * @param string $string
     * @return mixed
     * @see unserialize()
     */
    public function unserialize($string)
    {
        return unserialize($string);
    }
}
