<?php

namespace Skvn\Event;

use Skvn\Base\Traits\ArrayOrObjectAccessImpl;
use Skvn\Base\Container;

class Event implements Contracts\Event, \ArrayAccess
{
    use ArrayOrObjectAccessImpl;

    protected $payload;
    protected $container;

    function __construct($payload = [])
    {
        $this->payload = $payload;
        $this->container = Container :: getInstance();
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