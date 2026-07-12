<?php

namespace Domain\Atendimento\Events;

use Domain\Atendimento\Entities\OS;

/**
 * Evento de domínio disparado sempre que o status de uma OS é alterado.
 */
class StatusOSAlterado
{
    public function __construct(
        public readonly OS $os,
        public readonly ?int $statusAnteriorId,
        public readonly int $statusNovoId,
    ) {}
}
