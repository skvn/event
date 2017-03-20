<?php

namespace Skvn\Event\Queue;

use Skvn\Event\Contracts\BackgroundEvent;

class DatabaseConnection extends Connection
{
    public function push($event)
    {
        $event['queue_name'] = $this->queueName;
        $event['state'] = BackgroundEvent :: STATE_NEW;
        $event['created_at'] = time();
        $this->app['db']->insert($this->config['table'], $event);
    }

    public function pop()
    {
        $next = $this->app['db']
            ->selectOne('select * from ' . $this->config['table'] . ' where queue_name=? and state=? and pid is null order by id limit 1', [
                $this->queueName,
                BackgroundEvent :: STATE_NEW
            ]);
        if ($next)
        {
            $this->app['db']->update($this->config['table'], [
                'state' => BackgroundEvent :: STATE_PROCESS,
                'pid' => posix_getpid(),
                'id' => $next['id']
            ]);
            return $next;
        }
    }

    public function fetch($limit)
    {
        $this->app['db']->statement('update ' . $this->config['table'] . ' set state=?, pid=? where queue_name=? and state=? and pid is null order by id limit ' . $limit, [
            BackgroundEvent :: STATE_PROCESS,
            posix_getpid(),
            $this->queueName,
            BackgroundEvent :: STATE_NEW
        ]);
        return $this->app['db']->select('select * from ' . $this->config['table'] . ' where queue_name=? and state=? and pid=?', [
            $this->queueName,
            BackgroundEvent :: STATE_PROCESS,
            posix_getpid()
        ]);
    }

    public function success($ids)
    {
        $ids = (array) $ids;
        $events = $this->app['db']->select('select * from ' . $this->config['table'] . ' where id in (?)', [$ids]);
//        $this->app['db']->statement('update ' . $this->config['table'] . ' set state=? where id in (?)', [
//            BackgroundEvent :: STATE_DONE,
//            $ids
//        ]);
        $this->app['db']->statement('delete from ' . $this->config['table'] . ' where id in (?)', [$ids]);
        return $events;
    }

    public function fail($ids, $error)
    {
        $ids = (array) $ids;
        $this->app['db']->statement('update ' . $this->config['table'] . ' set state=?, error=? where id in (?)', [
            BackgroundEvent :: STATE_FAILED,
            $error,
            $ids
        ]);
    }
}