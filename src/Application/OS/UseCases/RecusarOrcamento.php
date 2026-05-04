<?php

namespace Application\OS\UseCases;

use App\Models\OSOrcamento as OSOrcamentoModel;
use RuntimeException;

class RecusarOrcamento
{
    public function executar(int $osId): OSOrcamentoModel
    {
        $orcamento = OSOrcamentoModel::where('os_id', $osId)->firstOrFail();

        if ($orcamento->status !== 'pendente') {
            throw new RuntimeException('Somente orçamentos pendentes podem ser recusados.');
        }

        $orcamento->status = 'recusado';
        $orcamento->save();

        return $orcamento;
    }
}
