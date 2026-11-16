<?php

namespace Domain\Atendimento\Saga;

use DomainException;

class TransicaoInvalida extends DomainException
{
    public static function de(EstadoSaga $estado, string $evento): self
    {
        return new self("Saga em {$estado->value} não aceita {$evento}.");
    }
}
