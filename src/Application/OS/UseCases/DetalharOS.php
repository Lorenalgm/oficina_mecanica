<?php

namespace Application\OS\UseCases;

use App\Models\OS as OSModel;

class DetalharOS
{
    public function executar(int $id): ?OSModel
    {
        return OSModel::with([
            'cliente',
            'veiculo',
            'statusAtual',
            'servicos.servico',
            'servicos.insumos.insumo',
            'historicoStatus.status',
            'orcamento',
        ])->find($id);
    }
}
