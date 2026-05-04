<?php

namespace App\Http\Resources;

use App\Models\OS;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OSResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OS $os */
        $os = $this->resource;

        $data = [
            'id' => $os->id,
            'cliente_id' => $os->cliente_id,
            'veiculo_id' => $os->veiculo_id,
            'descricao_problema' => $os->descricao_problema,
            'status_atual' => $os->statusAtual ? ['id' => $os->statusAtual->id, 'nome' => $os->statusAtual->nome] : null,
            'created_at' => $os->created_at,
        ];

        if ($os->relationLoaded('cliente')) {
            $data['cliente'] = $os->cliente ? ['id' => $os->cliente->id, 'nome' => $os->cliente->nome] : null;
        }

        if ($os->relationLoaded('veiculo')) {
            $data['veiculo'] = $os->veiculo ? ['id' => $os->veiculo->id, 'placa' => $os->veiculo->placa] : null;
        }

        if ($os->relationLoaded('servicos')) {
            $data['servicos'] = $os->servicos->map(fn ($osServico) => [
                'id' => $osServico->id,
                'servico_id' => $osServico->servico_id,
                'servico' => $osServico->servico ? ['id' => $osServico->servico->id, 'nome' => $osServico->servico->nome, 'valor' => $osServico->servico->valor] : null,
                'insumos' => $osServico->relationLoaded('insumos') ? $osServico->insumos->map(fn ($osi) => [
                    'id' => $osi->id,
                    'insumo_id' => $osi->insumo_id,
                    'quantidade' => $osi->quantidade,
                    'insumo' => $osi->insumo ? ['id' => $osi->insumo->id, 'nome' => $osi->insumo->nome, 'valor' => $osi->insumo->valor] : null,
                ]) : [],
            ]);
        }

        if ($os->relationLoaded('historicoStatus')) {
            $data['historico_status'] = $os->historicoStatus->map(fn ($h) => [
                'id' => $h->id,
                'status_id' => $h->status_id,
                'status' => $h->status ? ['id' => $h->status->id, 'nome' => $h->status->nome] : null,
                'data_status' => $h->data_status,
            ]);
        }

        if ($os->relationLoaded('orcamento') && $os->orcamento) {
            $data['orcamento'] = [
                'id' => $os->orcamento->id,
                'valor_total' => $os->orcamento->valor_total,
                'status' => $os->orcamento->status,
                'data_orcamento' => $os->orcamento->data_orcamento,
                'data_aprovacao' => $os->orcamento->data_aprovacao,
            ];
        }

        return $data;
    }
}
