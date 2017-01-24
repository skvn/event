<?php

namespace Skvn\Event;

use Skvn\Base\Container;

class QueueDispatcher
{
    protected $container = null;
    protected $queues = [];


    function __construct()
    {
        $this->container = Container :: getInstance();
    }

    function push($event)
    {
        return $this->pushOn(method_exists($event, 'queue') ? $event->queue() : $this->getDefaultQueueName(), $event);
    }

    function pushOn($queue, $event)
    {
        return $this->connection($queue)->push([
            'event_name' => get_class($event),
            'payload' => $this->encode(method_exists($event, 'payload') ? $event->payload() : $event)
        ]);
    }

    function pop($queue)
    {
        $data = $this->connection($queue)->pop();
        return $this->createEvent($data['event_name'], $data['payload']);
    }

    function createEvent($name, $payload)
    {
        $event = $this->container->create($name);
        if (method_exists($event, 'payload')) {
            $event->payload($this->decode($payload));
        }
        return $event;
    }

    function fetch($queue, $limit = 10)
    {
        $data = $this->connection($queue)->fetch($limit);
        $events = [];
        foreach ($data as $row) {
            $events[] = $this->createEvent($row['event_name'], $row['payload']);
        }
        return $events;
    }

    function getDefaultQueueName()
    {

    }

    function encode($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE+JSON_UNESCAPED_SLASHES);
    }

    function decode($data)
    {
        return json_decode($data, true);
    }

    function connection($name = null)
    {
        if (is_null($name)) {
            $name = $this->getDefaultQueueName();
        }
        if (!isset($this->queues[$name])) {
            throw new Exceptions\QueueException('Queue not found');
        }
        return $this->queues[$name];
    }

    function registerQueue($name, Queue\Connection $connection)
    {
        $this->queues[$name] = $connection;
    }
}