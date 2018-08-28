<?php

namespace Skvn\Event\Events;

use Skvn\Event\Event as BaseEvent;


/**
 * Class QueueDone
 * @package Skvn\Event\Events
 *
 * @property \Skvn\App\Application $app
 * @property array $ids
 * @property mixed $result
 * @property string $queue
 * @property string $class
 * @property \Skvn\Event\Event $srcEvent
 */
class QueueDone extends BaseEvent
{

}