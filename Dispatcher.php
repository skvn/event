<?php

namespace Skvn\Event;

use Skvn\Base\Exceptions\Exception;
use Skvn\Base\Container;

class Dispatcher
{
    protected $listeners = [];
    protected $container = null;

    function __construct()
    {
        $this->container = new Container();
    }


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

    public function trigger(Contracts\Event $event)
    {
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

        foreach ($this->listeners[get_class($event)] ?? [] as $listener) {
            $result = $this->callListener($listener, $event);
            if ($result === false) {
                return;
            }
            $responses[] = $result;
        }
        return $responses;
    }

    protected function queue(Contracts\Event $event)
    {

    }

    protected function browserify(Contracts\Event $event)
    {

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
            $obj = $this->container->make($class);
            return call_user_func([$obj, $method], $event);
        }
        throw new Exception('Unknown listener format');
    }






    /**
     * Create a callable for putting an event handler on the queue.
     *
     * @param  string  $class
     * @param  string  $method
     * @return \Closure
     */
    protected function createQueuedHandlerCallable($class, $method)
    {
        return function () use ($class, $method) {
            $arguments = $this->cloneArgumentsForQueueing(func_get_args());

            if (method_exists($class, 'queue')) {
                $this->callQueueMethodOnHandler($class, $method, $arguments);
            } else {
                $this->resolveQueue()->push('Illuminate\Events\CallQueuedHandler@call', [
                    'class' => $class, 'method' => $method, 'data' => serialize($arguments),
                ]);
            }
        };
    }

    /**
     * Clone the given arguments for queueing.
     *
     * @param  array  $arguments
     * @return array
     */
    protected function cloneArgumentsForQueueing(array $arguments)
    {
        return array_map(function ($a) {
            return is_object($a) ? clone $a : $a;
        }, $arguments);
    }

    /**
     * Call the queue method on the handler class.
     *
     * @param  string  $class
     * @param  string  $method
     * @param  array  $arguments
     * @return void
     */
    protected function callQueueMethodOnHandler($class, $method, $arguments)
    {
        $handler = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        $handler->queue($this->resolveQueue(), 'Illuminate\Events\CallQueuedHandler@call', [
            'class' => $class, 'method' => $method, 'data' => serialize($arguments),
        ]);
    }

}
