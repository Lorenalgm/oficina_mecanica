<?php

namespace Application\Veiculo\UseCases;

use Domain\Identidade\Entities\Veiculo;
use Domain\Identidade\Repositories\VeiculoRepository;

class BuscarVeiculo
{
    public function __construct(private VeiculoRepository $repositorio) {}

    public function executar(int $id): ?Veiculo
    {
        return $this->repositorio->findById($id);
    }
}
