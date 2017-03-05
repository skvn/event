<?php

namespace Skvn\Event\Events;

use Skvn\Event\Event as BaseEvent;


/**
 * Class QueueFail
 * @package Skvn\Event\Events
 *
 * @property \Skvn\App\Application $app
 * @property array $ids
 * @property string $error
 */
class QueueFail extends BaseEvent
{

}