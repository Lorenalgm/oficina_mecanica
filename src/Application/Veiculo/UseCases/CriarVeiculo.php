<?php

namespace Application\Veiculo\UseCases;

use Domain\Identidade\Entities\Veiculo;
use Domain\Identidade\Repositories\VeiculoRepository;
use Domain\Shared\ValueObjects\Placa;

class CriarVeiculo
{
    public function __construct(private VeiculoRepository $repositorio) {}

    public function executar(string $placa, string $marca, string $modelo, int $ano, int $clienteId): Veiculo
    {
        $veiculo = new Veiculo(
            id: null,
            placa: new Placa($placa),
            marca: $marca,
            modelo: $modelo,
            ano: $ano,
            clienteId: $clienteId,
        );

        return $this->repositorio->save($veiculo);
    }
}
