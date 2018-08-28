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
 * @property string $class
 * @property \Skvn\Event\Event $srcEvent
 */
class QueueFail extends BaseEvent
{

}