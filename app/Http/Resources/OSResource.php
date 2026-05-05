<?php

namespace App\Http\Resources;

use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Entities\OSOrcamento;
use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Entities\OSStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OSResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OS $os */
        $os = $this->resource;

        $data = [
            'id' => $os->getId(),
            'cliente_id' => $os->getClienteId(),
            'veiculo_id' => $os->getVeiculoId(),
            'descricao_problema' => $os->getDescricaoProblema(),
            'status_atual' => ['id' => $os->getStatusAtualId(), 'nome' => $os->getStatusAtualNome()],
            'created_at' => $os->getCreatedAt(),
        ];

        if ($os->getClienteNome() !== null) {
            $data['cliente'] = ['id' => $os->getClienteId(), 'nome' => $os->getClienteNome()];
        }

        if ($os->getVeiculoPlaca() !== null) {
            $data['veiculo'] = ['id' => $os->getVeiculoId(), 'placa' => $os->getVeiculoPlaca()];
        }

        if ($os->getServicos() !== null) {
            $data['servicos'] = array_map(function (OSServico $sv) {
                return [
                    'id' => $sv->getId(),
                    'servico_id' => $sv->getServicoId(),
                    'servico' => $sv->getServicoNome() !== null
                        ? ['id' => $sv->getServicoId(), 'nome' => $sv->getServicoNome(), 'valor' => $sv->getServicoValor()]
                        : null,
                    'insumos' => array_map(fn(OSServicoInsumo $osi) => [
                        'id' => $osi->getId(),
                        'insumo_id' => $osi->getInsumoId(),
                        'quantidade' => $osi->getQuantidade(),
                        'insumo' => $osi->getInsumoNome() !== null
                            ? ['id' => $osi->getInsumoId(), 'nome' => $osi->getInsumoNome(), 'valor' => $osi->getInsumoValor()]
                            : null,
                    ], $sv->getInsumos() ?? []),
                ];
            }, $os->getServicos());
        }

        if ($os->getHistoricoStatus() !== null) {
            $data['historico_status'] = array_map(fn(OSStatus $h) => [
                'id' => $h->getId(),
                'status_id' => $h->getStatusId(),
                'status' => $h->getStatusNome() !== null
                    ? ['id' => $h->getStatusId(), 'nome' => $h->getStatusNome()]
                    : null,
                'data_status' => $h->getDataStatus(),
            ], $os->getHistoricoStatus());
        }

        if ($os->getOrcamento() !== null) {
            /** @var OSOrcamento $orc */
            $orc = $os->getOrcamento();
            $data['orcamento'] = [
                'id' => $orc->getId(),
                'valor_total' => $orc->getValorTotal(),
                'status' => $orc->getStatus(),
                'data_orcamento' => $orc->getDataOrcamento(),
                'data_aprovacao' => $orc->getDataAprovacao(),
            ];
        }

        return $data;
    }
}
