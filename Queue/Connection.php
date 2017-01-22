<?php

namespace Skvn\Event\Queue;

use Skvn\Base\Container;

abstract class Connection
{
    protected $queueName;
    protected $container;
    protected $config;


    function __construct($queue, $config = [])
    {
        $this->queueName = $queue;
        $this->container = Container :: getInstance();
    }

    abstract public function push($event);
    abstract public function pop();
    abstract public function fetch($limit);

}
