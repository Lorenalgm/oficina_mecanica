<?php

namespace Application\OS\UseCases;

use App\Models\OS as OSModel;
use App\Models\OSServico as OSServicoModel;
use App\Models\Servico as ServicoModel;
use RuntimeException;

class AdicionarServicoNaOS
{
    public function executar(int $osId, int $servicoId): OSServicoModel
    {
        if (!OSModel::find($osId)) {
            throw new RuntimeException("OS #{$osId} não encontrada.");
        }

        if (!ServicoModel::find($servicoId)) {
            throw new RuntimeException("Serviço #{$servicoId} não encontrado.");
        }

        return OSServicoModel::create([
            'os_id' => $osId,
            'servico_id' => $servicoId,
        ]);
    }
}
