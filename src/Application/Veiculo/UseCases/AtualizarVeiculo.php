<?php

namespace Application\Veiculo\UseCases;

use Domain\Identidade\Entities\Veiculo;
use Domain\Identidade\Repositories\VeiculoRepository;

class AtualizarVeiculo
{
    public function __construct(private VeiculoRepository $repositorio) {}

    public function executar(int $id, string $marca, string $modelo, int $ano): Veiculo
    {
        $veiculo = $this->repositorio->findById($id);

        if (!$veiculo) {
            throw new \RuntimeException("Veículo #{$id} não encontrado.");
        }

        $veiculo->atualizar($marca, $modelo, $ano);

        return $this->repositorio->save($veiculo);
    }
}
