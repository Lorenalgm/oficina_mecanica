<?php

namespace Infrastructure\Events;

use Domain\Shared\Events\DomainEventDispatcher;
use Illuminate\Contracts\Events\Dispatcher;

class LaravelEventDispatcher implements DomainEventDispatcher
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function dispatch(object $evento): void
    {
        $this->dispatcher->dispatch($evento);
    }
}
