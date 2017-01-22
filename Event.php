<?php

namespace Skvn\Event;

use Skvn\Base\Traits\ArrayAccessImpl;

class Event implements Contracts\Event, \ArrayAccess
{
    use ArrayAccessImpl;

    protected $payload;

    function __construct($payload = [])
    {
        $this->payload = $payload;
    }

    function payload()
    {
        return $this->payload;
    }

    function get($param)
    {
        return $this->payload[$param] ?? null;
    }




}