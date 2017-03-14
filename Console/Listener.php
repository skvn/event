<?php

namespace Skvn\Event\Console;

use Skvn\App\Events\ConsoleActionEvent;
use Skvn\Base\Helpers\Str;
use Skvn\Base\Traits\SelfDescribe;
use Skvn\Event\Events\Log as LogEvent;

/**
 * Listen to queue events
 * @package Skvn\App\Console
 */
class Listener extends ConsoleActionEvent
{
    use SelfDescribe;

    /**
     * Start separate listener on each defined queue
     */
    function actionStart()
    {
        $qlist = [];
        foreach ($this->app->queue->getRegisteredQueues() as $qname => $qdata) {
            $qlist[] = ['name' => $qname, 'pid' => 0, 'started' => 0, 'pipes' => [], 'proc' => null];
        }
        if ($this->checkProcess('queue.pid')) {
            $this->message('control', '<bold><red>Already running</red></bold>');
            return;
        }
        $this->writePid('queue.pid');
        while (true) {
            $this->stdout('<bold>' . number_format(memory_get_usage()) . '</bold>');
            foreach ($qlist as &$queue) {
                if (!$this->checkProcess('queue_' . $queue['name'] . '.pid') && $this->app->queue->shouldStart($queue['name'], $queue['started'])) {
                    $msg = 'Starting ' . $queue['name'] . '.......';
                    $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
                    $command = implode(' ', [
                        PHP_BINARY,
                        $this->app->request->getServer('SCRIPT_NAME'),
                        Str :: snake(Str :: classBasename($this)) . '/run',
                        $queue['name']
                    ]);
                    $this->message('control', $command);
                    $queue['proc'] = proc_open($command, $descriptors, $queue['pipes']);
                    foreach ($queue['pipes'] as $pipe) {
                        stream_set_blocking($pipe, 0);
                    }
                    if (is_resource($queue['proc'])) {
                        $status = proc_get_status($queue['proc']);
                        $queue['pid'] = $status['pid'];
                        $queue['started'] = time();
                        $msg .= 'DONE with pid ' . $status['pid'];
                    } else {
                        $msg .= 'FAILED';
                    }
                    $this->message('control', '<bold>' . $msg . '</bold>');
                    sleep(1);
                    foreach ($queue['pipes'] as $pipe) {
                        $c = stream_get_contents($pipe);
                        if (!empty($c)) {
                            $this->message('control', $c);
                        }
                    }
                } else {
                    foreach ($queue['pipes'] as $pipe) {
                        $c = stream_get_contents($pipe);
                        if (!empty($c)) {
                            $this->message('control', $c);
                        }
                    }
                }
            }
            sleep(5);
        }
    }

    /**
     * Start listener on single queue
     * @argument string *queue name of queue
     *
     * @throws \Skvn\Base\Exceptions\Exception
     */
    function actionRun()
    {
        declare(ticks=1);
        //$this->app['config']['database.log'] = true;
        $t = time();

        $queueName = $this->arguments[0];
        $this->writePid('queue_' . $queueName . '.pid');
        $config = $this->app->queue->getQueueConfig($queueName);
        if (!empty($config['discrete'])) {
            $this->message('control', 'Discrete handler ' . $queueName . ' started');
            $events = $this->app->queue->fetch($queueName, $config['limit'] ?? 10);
            $this->message($queueName, count($events) . ' events received');
            $classes = [];
            foreach ($events as $event) {
                try {
                    $this->app->events->callListeners($event);
                    if (!isset($classes[get_class($event)])) {
                        $classes[get_class($event)] = [];
                    }
                    $classes[get_class($event)][] = $event->id;
                } catch (\Exception $e) {
                    $this->app->queue->fail($queueName, $event->id, $e->getMessage());
                    $this->message($queueName, 'ERROR:' . $e->getMessage());
                }
            }

            $res = [];
            foreach ($classes as $class => $ids) {
                try {
                    $obj = new $class();
                    $info = $obj->commit();
                    if (!empty($info)) {
                        $res[] = $info;
                    }
                    $this->app->queue->success($queueName, $ids);
                }
                catch (\Exception $e) {
                    $this->app->queue->fail($queueName, $ids, $e->getMessage());
                    $this->message($queueName, 'ERROR:' . $e->getMessage());
                }
            }
            if (!empty($res)) {
                $this->message($queueName, implode(PHP_EOL, $res));
            }



            $this->message('control', 'Discrete handler ' . $queueName . ' exited');

            return;
        }

        $count = 0;

        while (true) {
            if (!empty($config['limit']) && $count >= $config['limit']) {
                $this->message('control',$queueName . ': ' . $count . ' events executed. Exiting.');
                break;
            }
            if (!empty($config['ttl']) && (time() - $t) > $config['ttl']) {
                $this->message('control', $queueName . ': ' . (time() - $t) . ' seconds worked. Exiting.');
                break;
            }
            if ($event = $this->app->queue->pop($queueName)) {
                try {
                    $this->message($queueName, 'Event ' . get_class($event) . ':' . $event->id . ' received');
                    $result = $this->app->events->callListeners($event);
                    $this->message($queueName, $result);
                    $this->app->queue->success($queueName, $event->id);
                }
                catch (\Exception $e) {
                    $this->app->queue->fail($queueName, $event->id, $e->getMessage());
                    $this->message($queueName, 'ERROR: ' . $e->getMessage());
                }
                $count++;
            } else {
                sleep(3);
            }
        }
    }

    protected function message($queue, $message)
    {
        $message = sprintf('%s [%s.%d] %s', date('H:i:s'), strtolower($queue), posix_getpid(), $message);
        $this->stdout($message);
        $this->app->triggerEvent(new LogEvent([
            'message' => strip_tags($message),
            'category' => 'queue/' . strtolower($queue)
        ]));
    }

    /**
     * Check listener status
     */
    function actionCheck()
    {
        if ($this->checkProcess('queue.pid')) {
            $this->stdout('<green><bold>Listener is UP</bold></green>');
        } else {
            $this->stdout('<red><bold>Listener id DOWN</bold></red>');
        }
    }

    protected function checkProcess($pidfile)
    {
        $pidfile = $this->app->getPath('@locks/' . $pidfile);
        if (file_exists($pidfile)) {
            $pid = intval(file_get_contents($pidfile));
            if (posix_getsid($pid) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function writePid($pidfile)
    {
        $pidfile = $this->app->getPath('@locks/' . $pidfile);
        if (!file_exists(dirname($pidfile))) {
            mkdir(dirname($pidfile), 0777, true);
        }
        file_put_contents($pidfile, posix_getpid());
    }
}