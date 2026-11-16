<?php

namespace Domain\Atendimento\Saga;

use DomainException;

class SagaJaAtiva extends DomainException
{
    public function __construct(public readonly SagaOS $saga)
    {
        parent::__construct("A OS #{$saga->osId} já tem uma Saga em andamento ({$saga->estado()->value}).");
    }
}
