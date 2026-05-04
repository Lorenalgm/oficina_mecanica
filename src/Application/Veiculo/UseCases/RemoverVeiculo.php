<?php

namespace Application\Veiculo\UseCases;

use Domain\Identidade\Repositories\VeiculoRepository;

class RemoverVeiculo
{
    public function __construct(private VeiculoRepository $repositorio) {}

    public function executar(int $id): void
    {
        $this->repositorio->delete($id);
    }
}
