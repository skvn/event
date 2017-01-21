<?php

namespace Skvn\Event;

class Event implements Contracts\Event, \ArrayAccess
{
    protected $payload;

    function __construct($payload = [])
    {
        $this->payload = $payload;
    }

    function offsetExists($offset)
    {
        return isset($this->payload[$offset]);
    }

    function offsetGet($offset)
    {
        return $this->payload[$offset] ?? null;
    }

    function offsetSet($offset, $value)
    {
        return false;
    }

    function offsetUnset($offset)
    {
        return false;
    }




}