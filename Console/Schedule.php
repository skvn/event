<?php

namespace Skvn\Event\Console;

use Skvn\App\Events\ConsoleActionEvent;
use Skvn\Base\Traits\SelfDescribe;
use Skvn\Base\Helpers\Str;
use Skvn\Base\Exceptions\ConsoleException;
use Skvn\Event\Contracts\ScheduledEvent;
use Skvn\Event\Events\Log;


/**
 * Execute console command according to time plan defined
 * @package Skvn\App\Console
 */

class Schedule extends ConsoleActionEvent
{
    use SelfDescribe;

    protected $defaultAction = "run";


    /**
     * Main entry. Check current time and execute commands planed for this minute
     * Must me executed by  * * * * * crontab as root
     */
    function actionRun()
    {
        $entries = $this->getScheduledEntries();
        foreach ($entries as $entry) {
            $command = $this->buildCommand($entry);
            exec($command);
            $this->app->triggerEvent(new Log(['message' => $command, 'category' => 'cron/start']));
        }

    }

    protected function buildCommand($entry)
    {
        $cmd = [];
        if (!empty($entry['user']) && $entry['user'] != 'root') {
            $cmd[] = 'sudo -u ' . $entry['user'];
        }
        $cmd[] = PHP_BINARY;
        $cmd[] = $this->app->request->getServer('SCRIPT_NAME');
        $cmd[] = Str :: snake(Str :: classBasename($entry['command'])) . '/' . $entry['action'];
        $cmd[] = '2>&1 >> ' . $this->app->getPath('@var/schedule_out.txt') . ' &';
        return implode(' ', $cmd);
    }

    protected function getScheduledEntries()
    {
        $entries = [];
        foreach ($this->app->getAvailableCommands() as $command) {
            if ($command instanceof ScheduledEvent) {
                foreach ($command->schedule() as $entry) {
                    if ($entry['time']->isDue() && $this->app->filterScheduledEntry($entry)) {
                        $entry['command'] = $command;
                        $entries[] = $entry;
                    }
                }
            }
        }
        return $entries;
    }




}