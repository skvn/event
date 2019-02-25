<?php

namespace Skvn\Event\Contracts;


interface BrowserEvent extends Event
{
    public function getClientEventName();
    public function getClientEventData();
}
