<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Filters\FiltroListagemOS;
use Domain\Atendimento\Repositories\OSRepository;

class ListarOS
{
    public function __construct(private OSRepository $repositorio) {}

    /**
     * @return OS[]
     */
    public function executar(FiltroListagemOS $filtro): array
    {
        return $this->repositorio->findAll($filtro);
    }
}
