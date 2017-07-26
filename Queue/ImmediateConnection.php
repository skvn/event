<?php

namespace Skvn\Event\Queue;

use Skvn\Event\Exceptions\QueueException;

class ImmediateConnection extends Connection
{
    protected $currentEvent = null;

    public function push($event)
    {
        $event['id'] = 0;
        $this->currentEvent = $event;
        $targetEvent = $this->app->queue->pop($this->queueName);
        if ($targetEvent) {
            $this->app->events->callListeners($targetEvent);
        }
        $this->currentEvent = null;
    }

    public function pop()
    {
        return $this->currentEvent;
    }

    public function fetch($limit)
    {
        throw new QueueException('Not implemented');
    }

    public function success($ids)
    {
        throw new QueueException('Not implemented');
    }

    public function fail($ids, $error)
    {
        throw new QueueException('Not implemented');
    }
}