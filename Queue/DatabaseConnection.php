<?php

namespace Skvn\Event\Queue;

class DatabaseConnection extends Connection
{
    public function push($event)
    {
        $event['queue_name'] = $this->queueName;
        $this->container['db']->insert($this->config['table'], $event);
    }

    public function pop()
    {

    }

    public function fetch($limit)
    {

    }
}