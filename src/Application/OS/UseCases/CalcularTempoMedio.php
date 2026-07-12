<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Repositories\OSRepository;

class CalcularTempoMedio
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(): ?float
    {
        return $this->repositorio->calcularTempoMedioMinutos();
    }
}
