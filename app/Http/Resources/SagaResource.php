<?php

namespace App\Http\Resources;

use Domain\Atendimento\Saga\Compensacao;
use Domain\Atendimento\Saga\SagaOS;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SagaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SagaOS $saga */
        $saga = $this->resource['saga'];

        return [
            'saga_id' => $saga->id,
            'os_id' => $saga->osId,
            'correlation_id' => $saga->correlationId,
            'estado' => $saga->estado()->value,
            'ativa' => $saga->ativa(),
            'orcamento' => [
                'id' => $saga->orcamentoId(),
                'valor_total' => $saga->valorTotal(),
                'link_aprovacao' => $saga->linkAprovacao(),
            ],
            'pagamento' => [
                'id' => $saga->pagamentoId(),
                'link_pagamento' => $saga->linkPagamento(),
            ],
            'execucao_id' => $saga->execucaoId(),
            'estoque_baixado' => $saga->estoqueBaixado(),
            'motivo_falha' => $saga->motivoFalha(),
            'compensacoes_pendentes' => array_map(fn (Compensacao $c) => $c->value, $saga->compensacoesPendentes()),
            'tentativas_compensacao' => $saga->tentativasCompensacao(),
            'passos' => array_map(fn (array $p) => [
                'passo' => $p['passo'],
                'detalhes' => (object) $p['detalhes'],
                'registrado_em' => $p['registrado_em'],
            ], $this->resource['passos']),
        ];
    }
}
