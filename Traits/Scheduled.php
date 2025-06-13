<?php

namespace Skvn\Event\Traits;

use Cron\CronExpression;

trait Scheduled
{
    protected function scheduleByString($str)
    {
        $parts = explode('#', $str);
        $method = array_shift($parts);
        return call_user_func_array([$this, $method], $parts);
    }

    protected function hourly($minutes = '0')
    {
        return $this->cron($minutes . ' * * * *');
    }


    protected function cron($expression)
    {
        return CronExpression::factory($expression);
    }

    protected function daily()
    {
        return $this->cron('0 0 * * *');
    }

    protected function at($time)
    {
        return $this->dailyAt($time);
    }

    protected function dailyAt($time)
    {
        $segments = explode(':', $time);
        return $this->cron(($segments[1] ?? '0') . ' ' . ($segments[0] ?? '0') . ' * * *');
    }

    protected function twiceDaily()
    {
        return $this->cron('0 1,13 * * *');
    }

    protected function weekdays()
    {
        return $this->cron('0 0 * * 1-5');
    }

    protected function mondays()
    {
        return $this->days(1);
    }

    protected function days(...$days)
    {
        return $this->cron('0 0 * * ' . implode(',', $days));
    }

    protected function tuesdays()
    {
        return $this->days(2);
    }

    protected function wednesdays()
    {
        return $this->days(3);
    }

    protected function thursdays()
    {
        return $this->days(4);
    }

    protected function fridays()
    {
        return $this->days(5);
    }

    protected function saturdays()
    {
        return $this->days(6);
    }

    protected function sundays()
    {
        return $this->days(0);
    }

    protected function weekly()
    {
        return $this->cron('0 0 * * 0');
    }

    protected function weeklyOn($day, $time = '0:0')
    {
        $segments = explode(':', $time);
        return $this->cron(($segments[1] ?? '0') . ' ' . ($segments[0] ?? '0') . ' * * ' . $day);
    }

    protected function monthly()
    {
        return $this->cron('0 0 1 * *');
    }

    protected function yearly()
    {
        return $this->cron('0 0 1 1 *');
    }

    protected function everyMinute()
    {
        return $this->cron('* * * * *');
    }

    protected function everyNMinutes($minutes)
    {
        return $this->cron('*/'.$minutes.' * * * *');
    }

    protected function everyFiveMinutes()
    {
        return $this->everyNMinutes(5);
    }

    protected function everyTenMinutes()
    {
        return $this->everyNMinutes(10);
    }

    protected function everyThirtyMinutes()
    {
        return $this->cron('0,30 * * * *');
    }


}