<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\OS as OSModel;
use Domain\Atendimento\Entities\OS;

class OSMapper
{
    public static function toEntity(OSModel $model): OS
    {
        return new OS(
            id: $model->id,
            clienteId: (int) $model->cliente_id,
            veiculoId: (int) $model->veiculo_id,
            statusAtualId: (int) $model->status_atual_id,
            descricaoProblema: $model->descricao_problema,
        );
    }

    public static function toArray(OS $os): array
    {
        return [
            'cliente_id' => $os->getClienteId(),
            'veiculo_id' => $os->getVeiculoId(),
            'status_atual_id' => $os->getStatusAtualId(),
            'descricao_problema' => $os->getDescricaoProblema(),
        ];
    }
}
