<?php

namespace Skvn\Event;

use Skvn\Base\Traits\AppHolder;
use Skvn\Base\Helpers\Str;

class QueueDispatcher
{
    use AppHolder;

    private $queues = [];
    private $availDrivers = [
        'database' => ['class' => Queue\DatabaseConnection::class],
        'immediate' => ['class' => Queue\ImmediateConnection::class]
    ];


    function __construct($config = [])
    {
        foreach ($config['queues'] ?? [] as $name => $qconfig) {
            $this->registerQueue($name, $qconfig);
        }
    }

    function push($event)
    {
        return $this->pushOn($event->queue(), $event);
    }

    function pushOn($queue, $event)
    {
        return $this->connection($queue)->push([
            'event_name' => get_class($event),
            'payload' => $this->encode(method_exists($event, 'payload') ? $event->payload() : $event),
            'delayed_to' => $event->delayedTo ?? null
        ]);
    }

    function pop($queue)
    {
        $data = $this->connection($queue)->pop();
        if ($data) {
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
        $event = $this->app->create($data['event_name']);
        if (method_exists($event, 'payload')) {
            $event->payload($this->decode($data['payload']));
        }
        $event->id = $data['id'];
        return $event;
    }
    
    function failEvent($queue, Event $event, $error)
    {
        $this->connection($queue)->fail([$event->id], $error);
        $this->app->triggerEvent(new Events\QueueFail([
            'srcEvent' => $event,
            'error' => $error
        ]));
    }

    function fail($queue, $ids, $error, $class = null)
    {
        $this->connection($queue)->fail($ids, $error);
        $this->app->triggerEvent(new Events\QueueFail([
            'ids' => (array) $ids,
            'error' => $error,
            'class' => $class
        ]));
    }
    
    function successEvent($queue, Event $event, $result = null)
    {
        $this->connection($queue)->success($event->id);
        $this->app->triggerEvent(new Events\QueueDone([
            'srcEvent' => $event,
            'result' => $result,
            'queue' => $queue,
        ]));
    }

    function success($queue, $ids, $result = null, $class = null)
    {
        $this->connection($queue)->success($ids);
        $this->app->triggerEvent(new Events\QueueDone([
            'ids' => (array) $ids,
            'result' => $result,
            'queue' => $queue,
            'class' => $class
        ]));
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

    private function connection($name = null)
    {
        $config = $this->getQueueConfig($name);
        if (is_null($this->queues[$name]['conn'])) {
            $this->queues[$name]['conn'] = $this->createConnection($name, $config);
        }
        return $this->queues[$name]['conn'];
    }

    private function createConnection($name, $config)
    {
        $driver = $config['driver'] ?? 'database';
        if (array_key_exists($driver, $this->availDrivers)) {
            $class = $this->availDrivers[$driver]['class'];
            $conn = new $class($name, $config);
        } else {
            $conn = $this->createCustomConnection($driver, $name, $config);
        }
        $conn->setApp($this->app);
        return $conn;
    }
    
    private function createCustomConnection($driver, $name, $config)
    {
        throw new Exceptions\QueueException('Custom drivers not supported');
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