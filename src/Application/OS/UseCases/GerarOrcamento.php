<?php

namespace Application\OS\UseCases;

use App\Models\OS as OSModel;
use App\Models\OSOrcamento as OSOrcamentoModel;
use App\Models\OSStatus as OSStatusModel;
use App\Models\Status;
use RuntimeException;

class GerarOrcamento
{
    public function executar(int $osId): OSOrcamentoModel
    {
        $os = OSModel::with(['servicos.servico', 'servicos.insumos.insumo'])->findOrFail($osId);

        if ($os->servicos->isEmpty()) {
            throw new RuntimeException('A OS não possui serviços para gerar orçamento.');
        }

        $valorTotal = 0.0;

        foreach ($os->servicos as $osServico) {
            $valorTotal += (float) $osServico->servico->valor;

            foreach ($osServico->insumos as $osServicoInsumo) {
                $valorTotal += (float) $osServicoInsumo->insumo->valor * $osServicoInsumo->quantidade;
            }
        }

        $orcamento = OSOrcamentoModel::create([
            'os_id' => $osId,
            'valor_total' => $valorTotal,
            'data_orcamento' => now(),
            'status' => 'pendente',
        ]);

        $statusAguardando = Status::where('nome', 'Aguardando aprovação')->first();
        if ($statusAguardando) {
            $os->status_atual_id = $statusAguardando->id;
            $os->save();
            OSStatusModel::create([
                'os_id' => $osId,
                'status_id' => $statusAguardando->id,
                'data_status' => now(),
            ]);
        }

        return $orcamento;
    }
}
