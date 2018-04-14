<?php

namespace Skvn\Event;

use Skvn\Base\Exceptions\Exception;
use Skvn\Base\Container;
use Skvn\Base\Traits\AppHolder;

class EventDispatcher
{
    use AppHolder;

    protected $listeners = [];


    public function listen($class, $listener, $prepend = false)
    {
        if (!isset($this->listeners[$class])) {
            $this->listeners[$class] = [];
        }
        if ($prepend) {
            array_unshift($this->listeners[$class], $listener);
        } else {
            array_push($this->listeners[$class], $listener);
        }
    }

    public function trigger(Contracts\Event $event, $immediate = false)
    {
        if ($immediate) {
            return $event->handle();
        }
        if ($event instanceof Contracts\BrowserEvent) {
            $this->browserify($event);
        }

        if ($event instanceof Contracts\BackgroundEvent) {
            return $this->queue($event);
        }

        return $this->callListeners($event);
    }

    public function callListeners(Contracts\Event $event)
    {
        $responses = [];

        if ($event instanceof Contracts\SelfHandlingEvent) {
            $responses[] = $event->handle();
        } elseif (!isset($this->listeners[get_class($event)])) {
            return false;
        }

        foreach ($this->listeners[get_class($event)] ?? [] as $listener) {
            $result = $this->callListener($listener, $event);
            if ($result === false) {
                return false;
            }
            $responses[] = $result;
        }
        return $this->handleResponses($event, $responses);
    }

    protected function handleResponses(Contracts\Event $event, $responses)
    {
        //$responses = array_filter($responses, function($item){return !empty($item);});
        if ($event instanceof Contracts\SelfHandlingEvent) {
            if (count($responses) > 0) {
                return array_shift($responses);
            }
        }
        return $responses;
    }

    protected function queue(Contracts\Event $event)
    {
        $this->app['queue']->push($event);
    }

    protected function browserify(Contracts\Event $event)
    {
        $this->app['ws']->push($event);
    }

    protected function callListener($listener, Contracts\Event $event)
    {
        if ($listener instanceof \Closure) {
            return $listener($event);
        }
        if (is_callable($listener)) {
            return call_user_func($listener, $event);
        }
        if (is_string($listener) && strpos($listener, '@') !== false) {
            list($class, $method) = explode('@', $listener);
            $obj = $this->app->make($class);
            return call_user_func([$obj, $method], $event);
        }
        throw new Exception('Unknown listener format');
    }


}
