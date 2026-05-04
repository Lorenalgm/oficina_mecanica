<?php

namespace Application\OS\UseCases;

use App\Models\Insumo as InsumoModel;
use App\Models\OSServico as OSServicoModel;
use App\Models\OSServicoInsumo as OSServicoInsumoModel;
use RuntimeException;

class AdicionarInsumoNoServico
{
    public function executar(int $osServicoId, int $insumoId, int $quantidade): OSServicoInsumoModel
    {
        if (!OSServicoModel::find($osServicoId)) {
            throw new RuntimeException("OSServico #{$osServicoId} não encontrado.");
        }

        if (!InsumoModel::find($insumoId)) {
            throw new RuntimeException("Insumo #{$insumoId} não encontrado.");
        }

        return OSServicoInsumoModel::create([
            'os_servico_id' => $osServicoId,
            'insumo_id' => $insumoId,
            'quantidade' => $quantidade,
        ]);
    }
}
