<?php

namespace Application\OS\UseCases;

use App\Models\OS as OSModel;
use Domain\Atendimento\Entities\OS;
use Infrastructure\Persistence\Eloquent\OSMapper;

class DetalharOS
{
    public function executar(int $id): ?OS
    {
        $model = OSModel::with([
            'cliente',
            'veiculo',
            'statusAtual',
            'servicos.servico',
            'servicos.insumos.insumo',
            'historicoStatus.status',
            'orcamento',
        ])->find($id);

        return $model ? OSMapper::toDetailEntity($model) : null;
    }
}
