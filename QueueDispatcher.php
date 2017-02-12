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
        if ($data)
        {
            return $this->createEvent($data);
        }
    }

    function fetch($queue, $limit = 10)
    {
        $data = $this->connection($queue)->fetch($limit);
        $events = [];
        foreach ($data as $row) {
            $events[] = $this->createEvent($row);
        }
        return $events;
    }

    protected function createEvent($data)
    {
        $event = $this->container->create($data['event_name']);
        if (method_exists($event, 'payload')) {
            $event->payload($this->decode($data['payload']));
        }
        $event->id = $data['id'];
        return $event;
    }

    function fail($queue, $ids, $error)
    {
        $this->connection($queue)->fail($ids, $error);
    }

    function success($queue, $ids)
    {
        $this->connection($queue)->success($ids);
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

    protected function connection($name = null)
    {
        if (is_null($name)) {
            $name = $this->getDefaultQueueName();
        }
        if (!isset($this->queues[$name])) {
            throw new Exceptions\QueueException('Queue ' . $name . ' not defined');
        }
        if (is_null($this->queues[$name]['conn'])) {
            $this->queues[$name]['conn'] = $this->createConnection($name, $this->queues[$name]['config']);
        }
        return $this->queues[$name]['conn'];
    }

    protected function createConnection($name, $config)
    {
        return new Queue\DatabaseConnection($name, $config);
    }

    function registerQueue($name, $config)
    {
        $this->queues[$name] = ['name' => $name, 'config' => $config, 'conn' => null];
    }

    function getQueueConfig($name = null)
    {
        if (is_null($name)) {
            $name = $this->getDefaultQueueName();
        }
        if (!isset($this->queues[$name])) {
            throw new Exceptions\QueueException('Queue ' . $name . 'not defined');
        }
        return $this->queues[$name]['config'];
    }

    function getRegisteredQueues()
    {
        return $this->queues;
    }

    function shouldStart($name, $prev)
    {
        $config = $this->getQueueConfig($name);
        if (!empty($config['discrete'])) {
            return (time()-$prev) > $config['discrete'];
        } else {
            return true;
        }

    }

}