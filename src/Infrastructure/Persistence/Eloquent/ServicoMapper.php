<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Servico as ServicoModel;
use Domain\Catalogo\Entities\Servico;

class ServicoMapper
{
    public static function toEntity(ServicoModel $model): Servico
    {
        return new Servico(
            id: $model->id,
            nome: $model->nome,
            valor: (float) $model->valor,
        );
    }

    public static function toArray(Servico $servico): array
    {
        return [
            'nome' => $servico->getNome(),
            'valor' => $servico->getValor(),
        ];
    }
}
