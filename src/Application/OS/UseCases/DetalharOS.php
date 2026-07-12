<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Repositories\OSRepository;

class DetalharOS
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(int $id): ?OS
    {
        return $this->repositorio->findById($id);
    }
}
