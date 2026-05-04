<?php

namespace Application\Insumo\UseCases;

use Domain\Catalogo\Entities\Insumo;
use Domain\Catalogo\Repositories\InsumoRepository;

class BuscarInsumo
{
    public function __construct(private InsumoRepository $repositorio) {}

    public function executar(int $id): ?Insumo
    {
        return $this->repositorio->findById($id);
    }
}
