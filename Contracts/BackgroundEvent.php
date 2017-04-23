<?php

namespace Skvn\Event\Contracts;


interface BackgroundEvent extends Event
{
    const STATE_NEW = 1;
    const STATE_PROCESS = 2;
    const STATE_FAILED = 3;
    const STATE_DONE = 4;

    function queue();
}
