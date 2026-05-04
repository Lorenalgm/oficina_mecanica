<?php

namespace Application\Veiculo\UseCases;

use Domain\Identidade\Repositories\VeiculoRepository;

class ListarVeiculos
{
    public function __construct(private VeiculoRepository $repositorio) {}

    public function executar(): array
    {
        return $this->repositorio->findAll();
    }
}
