<?php

namespace UserlandSession\Serializer;

interface SerializerInterface
{
    public function serialize($val);

    public function unserialize($string);
}