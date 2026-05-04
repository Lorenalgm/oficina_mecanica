<?php

namespace Application\Insumo\UseCases;

use Domain\Catalogo\Repositories\InsumoRepository;

class ListarInsumos
{
    public function __construct(private InsumoRepository $repositorio) {}

    public function executar(): array
    {
        return $this->repositorio->findAll();
    }
}
