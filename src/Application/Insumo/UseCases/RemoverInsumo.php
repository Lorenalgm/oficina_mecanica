<?php

namespace Application\Insumo\UseCases;

use Domain\Catalogo\Repositories\InsumoRepository;

class RemoverInsumo
{
    public function __construct(private InsumoRepository $repositorio) {}

    public function executar(int $id): void
    {
        $this->repositorio->delete($id);
    }
}
