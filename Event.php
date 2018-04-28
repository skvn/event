<?php

namespace Skvn\Event;

use Skvn\Base\Traits\ArrayOrObjectAccessImpl;
use Skvn\Base\Container;

class Event implements Contracts\Event, \ArrayAccess
{
    use ArrayOrObjectAccessImpl;

    protected $payload;
    protected $container;
    public $app;
    public $id;

    function __construct($payload = [])
    {
        $this->payload = $payload;
        $this->app = $this->container = Container :: getInstance();
    }

    function payload($payload = null)
    {
        if (!is_null($payload)) {
            $this->payload = $payload;
        }
        return $this->payload;
    }

    function get($param)
    {
        return $this->payload[$param] ?? null;
    }
    
    function set($param, $value)
    {
        $this->payload[$param] = $value;
    }





}