<?php

namespace Skvn\Event\Console;

use Skvn\App\Events\ConsoleActionEvent;
use Skvn\Base\Traits\SelfDescribe;
use Skvn\Base\Helpers\Str;
use Skvn\Base\Exceptions\ConsoleException;
use Skvn\Event\Contracts\ScheduledEvent;
use Skvn\Event\Events\Log;
use Skvn\Event\Events\SchedulerDone;
use Skvn\Base\Helpers\File;
use Skvn\Event\Traits\Scheduled;



/**
 * Execute console command according to time plan defined
 * @package Skvn\App\Console
 */

class Schedule extends ConsoleActionEvent
{
    use SelfDescribe;
    use Scheduled;

    protected $defaultAction = "run";

    /**
     * Main entry. Check current time and execute commands planed for this minute
     * Must me executed by  * * * * * crontab as root
     */
    function actionRun()
    {
        $t = microtime(true);
        $entries = $this->getScheduledEntries();
        $this->app->triggerEvent(new Log(['message' => 'Commands fetched ' . round(microtime(true) - $t, 5), 'category' => 'cron/starter']));
        foreach ($entries as $entry) {
            $command = $this->buildCommand($entry);
            exec($command);
            $this->app->triggerEvent(new Log(['message' => $command  . '(' . microtime(true) . ')', 'category' => 'cron/start']));
        }
        $this->app->triggerEvent(new Log(['message' => 'Commands executed ' . round(microtime(true) - $t, 5), 'category' => 'cron/starter']));
        $this->app->triggerEvent(new SchedulerDone());

    }

    /**
     * Dump current crontabs scheme
     */
    function actionTabs()
    {
        $this->stdout('Dumping all tabs....');
        $this->stdout('');
        $this->stdout('');
        $chost = null;

        array_map(function($item) use (&$chost){
            if ($chost != $item['host']) {
                $this->stdout('');
                $this->stdout('<bold>-----===== Dumping host ' . $item['host'] . ' =====-----</bold>');
                $this->stdout('');
                $chost = $item['host'];
            }
            $opts = [];
            foreach ($item['options'] ?? [] as $k => $v) {
                $opts[] = '--' . $k . '=' . (is_array($v) ? implode(',', $v) : $v);
            }
            $this->stdout($item['host'] . ': ' .
                    str_pad($item['time']->getExpression(), 16, ' ') . ' ' .
                    str_pad($item['user'], 7, ' ') . ' ' .
                    '<cyan>' . (!empty($item['cmd']) ? $item['cmd'] : (Str :: snake(Str :: classBasename($item['command'])) . '/' . $item['action'])) . '</cyan> ' .
                    implode(' ', $opts));
        }, $this->getScheduledEntries(true));
    }

    function actionState()
    {
        $list = File :: ls($this->app->getPath('@locks'), ['paths' => true]);
        foreach ($list as $file) {
            if (preg_match('#cron\.(\d+)$#', $file, $matches)) {
                if (file_exists($file)) {
                    $info = json_decode(file_get_contents($file), true);
                    $ctime = filemtime($file);
                    
                    $old = error_reporting(0);
                    $state = pcntl_getpriority($matches[1]) === false ? "KILLED" : "ALIVE";
                    error_reporting($old);
                    
                    if (isset($this->options['clean'])) {
                        if ($state == "KILLED") {
                            unlink($file);
                            $state = "REMOVED";
                        }
                    }
                    $this->stdout(str_pad($matches[1], 5, ' ').' '.str_pad($state, 7, ' ') . ' ('.gmdate("H:i:s", time()-$ctime).') ' . $info['command'].'('.json_encode($info['options'] ?? []) . ')');
                }
            }
        }

    }

    protected function buildCommand($entry)
    {
        $cmd = [];
        if (!empty($entry['user']) && $entry['user'] != 'root') {
            $cmd[] = '/usr/local/bin/sudo -u ' . $entry['user'];
        }
        $cmd[] = PHP_BINARY;
        $cmd[] = $this->app->request->getServer('SCRIPT_NAME');
        if (!empty($entry['cmd'])) {
            $cmd[] = $entry['cmd'];
        } else {
            $cmd[] = Str :: snake(Str :: classBasename($entry['command'])) . '/' . $entry['action'];
        }
        foreach ($entry['options'] ?? [] as $k => $v) {
            $cmd[] = '--' . $k . '=' . (is_array($v) ? implode(',', $v) : $v);
        }
        $cmd[] = '--notify --locks --cron';
        $cmd[] = '>> ' . $this->app->getPath('@var/schedule_out.txt') . ' 2>&1 &';
        return implode(' ', $cmd);
    }

    protected function getScheduledEntries($all = false)
    {
        $entries = [];
        foreach ($this->app->getAvailableCommands() as $command) {
            if ($command instanceof ScheduledEvent) {
                foreach ($command->schedule() as $entry) {
                    if ($all || $entry['time']->isDue() && $this->app->filterScheduledEntry($entry)) {
                        $entry['command'] = $command;
                        $entries[] = $entry;
                    }
                }
            }
        }
        if ($all) {
            $entries = $this->app->appendScheduledEntries($entries);
            usort($entries, function($a, $b){
                $at = explode(' ', $a['time']->getExpression());
                $bt = explode(' ', $b['time']->getExpression());
                $wa = $a['host']*1000000;
                $wb = $b['host']*1000000;
                if ($at[2] != '*') $wa += $at[2] * 100000;
                if ($bt[2] != '*') $wb += $bt[2] * 100000;
                if ($at[4] != '*') $wa += $at[4] * 10000;
                if ($bt[4] != '*') $wb += $bt[4] * 10000;
                if ($at[1] != '*') $wa += $at[1] * 60 + $at[0] + 100;
                if ($bt[1] != '*') $wb += $bt[1] * 60 + $bt[0] + 100;
                if (Str :: pos('*', $at[0]) === false) {
                    $wa += 70 + $at[0];
                } elseif ($at[0] !== '*') {
                    $wa += intval(str_replace('*/', '', $at[0]));
                }
                if (Str :: pos('*', $bt[0]) === false) {
                    $wb += 70 + $bt[0];
                } elseif ($bt[0] !== '*') {
                    $wb += intval(str_replace('*/', '', $bt[0]));
                }
                return $wa <=> $wb;
            });
        } else {
            foreach ($this->app->appendScheduledEntries([]) as $entry) {
                if ($entry['time']->isDue() && $this->app->filterScheduledEntry($entry)) {
                    $entries[] = $entry;
                }
            }
        }
        return $entries;
    }




}