<?php

namespace Domain\Shared\Events;

/**
 * Porta para publicação de eventos de domínio, mantendo a camada de
 * Aplicação/Domínio livre de dependências de framework (Illuminate).
 */
interface DomainEventDispatcher
{
    public function dispatch(object $evento): void;
}
