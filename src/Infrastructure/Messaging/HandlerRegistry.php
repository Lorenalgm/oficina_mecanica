<?php

namespace Infrastructure\Messaging;

use Closure;
use Domain\Shared\Messaging\MessageHandler;

class HandlerRegistry
{
    /**
     * @param array<string, class-string<MessageHandler>> $handlers tipo => classe
     * @param Closure(class-string<MessageHandler>): MessageHandler $resolver
     */
    public function __construct(
        private array $handlers,
        private Closure $resolver,
    ) {}

    public function para(string $type): ?MessageHandler
    {
        $classe = $this->handlers[$type] ?? null;

        return $classe === null ? null : ($this->resolver)($classe);
    }
}
