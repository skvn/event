<?php

namespace Skvn\Event\Queue;

use Skvn\Base\Traits\AppHolder;

abstract class Connection
{
    use AppHolder;

    protected $queueName;
    protected $config;


    function __construct($queue, $config = [])
    {
        $this->queueName = $queue;
        $this->config = $config;
    }

    abstract public function push($event);
    abstract public function pop();
    abstract public function fetch($limit);
    abstract public function fail($ids, $error);
    abstract public function success($ids);


}
