<?php

namespace Skvn\Event\Events;

use Skvn\Event\Event as BaseEvent;


/**
 * Class Log
 * @package Skvn\Event\Events
 *
 * @property \Skvn\App\Application $app
 * @property string $mesage
 * @property string $category
 */
class Log extends BaseEvent
{

}