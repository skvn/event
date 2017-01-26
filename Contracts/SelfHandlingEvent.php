<?php

namespace Skvn\Event\Contracts;


interface SelfHandlingEvent extends Event
{
    function handle();
}
