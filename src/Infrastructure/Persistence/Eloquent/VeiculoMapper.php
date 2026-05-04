<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Veiculo as VeiculoModel;
use Domain\Identidade\Entities\Veiculo;
use Domain\Shared\ValueObjects\Placa;

class VeiculoMapper
{
    public static function toEntity(VeiculoModel $model): Veiculo
    {
        return new Veiculo(
            id: $model->id,
            placa: new Placa($model->placa),
            marca: $model->marca,
            modelo: $model->modelo,
            ano: (int) $model->ano,
            clienteId: (int) $model->cliente_id,
        );
    }

    public static function toArray(Veiculo $veiculo): array
    {
        return [
            'placa' => $veiculo->getPlaca()->getValue(),
            'marca' => $veiculo->getMarca(),
            'modelo' => $veiculo->getModelo(),
            'ano' => $veiculo->getAno(),
            'cliente_id' => $veiculo->getClienteId(),
        ];
    }
}
