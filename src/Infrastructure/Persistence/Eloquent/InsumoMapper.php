<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Insumo as InsumoModel;
use Domain\Catalogo\Entities\Insumo;

class InsumoMapper
{
    public static function toEntity(InsumoModel $model): Insumo
    {
        return new Insumo(
            id: $model->id,
            nome: $model->nome,
            valor: (float) $model->valor,
            quantidadeEstoque: (int) $model->quantidade_estoque,
        );
    }

    public static function toArray(Insumo $insumo): array
    {
        return [
            'nome' => $insumo->getNome(),
            'valor' => $insumo->getValor(),
            'quantidade_estoque' => $insumo->getQuantidadeEstoque(),
        ];
    }
}
