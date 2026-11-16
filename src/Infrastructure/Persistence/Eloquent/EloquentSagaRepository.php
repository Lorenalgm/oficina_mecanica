<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Saga as SagaModel;
use App\Models\SagaPasso as SagaPassoModel;
use Domain\Atendimento\Saga\Compensacao;
use Domain\Atendimento\Saga\EstadoSaga;
use Domain\Atendimento\Saga\SagaOS;
use Domain\Atendimento\Saga\SagaRepository;

class EloquentSagaRepository implements SagaRepository
{
    private const ESTADOS_ENCERRADOS = ['concluida', 'compensada', 'compensacao_falhou'];

    public function salvar(SagaOS $saga): void
    {
        SagaModel::updateOrCreate(['id' => $saga->id], [
            'os_id' => $saga->osId,
            'correlation_id' => $saga->correlationId,
            'estado' => $saga->estado()->value,
            'orcamento_id' => $saga->orcamentoId(),
            'valor_total' => $saga->valorTotal(),
            'link_aprovacao' => $saga->linkAprovacao(),
            'pagamento_id' => $saga->pagamentoId(),
            'link_pagamento' => $saga->linkPagamento(),
            'execucao_id' => $saga->execucaoId(),
            'estoque_baixado' => $saga->estoqueBaixado(),
            'compensacoes_pendentes' => array_map(fn (Compensacao $c) => $c->value, $saga->compensacoesPendentes()),
            'tentativas_compensacao' => $saga->tentativasCompensacao(),
            'motivo_falha' => $saga->motivoFalha(),
            'ultimo_comando_id' => $saga->ultimoComandoId(),
        ]);
    }

    public function buscar(string $id): ?SagaOS
    {
        $model = SagaModel::lockForUpdate()->find($id);

        return $model ? $this->paraEntidade($model) : null;
    }

    public function buscarAtivaDaOS(int $osId): ?SagaOS
    {
        $model = SagaModel::where('os_id', $osId)
            ->whereNotIn('estado', self::ESTADOS_ENCERRADOS)
            ->lockForUpdate()
            ->first();

        return $model ? $this->paraEntidade($model) : null;
    }

    public function buscarUltimaDaOS(int $osId): ?SagaOS
    {
        $model = SagaModel::where('os_id', $osId)->latest()->orderByDesc('id')->first();

        return $model ? $this->paraEntidade($model) : null;
    }

    public function registrarPasso(string $sagaId, string $passo, array $detalhes = []): void
    {
        SagaPassoModel::create([
            'saga_id' => $sagaId,
            'passo' => $passo,
            'detalhes' => $detalhes,
            'registrado_em' => now(),
        ]);
    }

    public function passos(string $sagaId): array
    {
        return SagaPassoModel::where('saga_id', $sagaId)->orderBy('id')->get()
            ->map(fn (SagaPassoModel $p) => [
                'passo' => $p->passo,
                'detalhes' => $p->detalhes,
                'registrado_em' => $p->registrado_em,
            ])->all();
    }

    private function paraEntidade(SagaModel $model): SagaOS
    {
        return new SagaOS(
            id: $model->id,
            osId: $model->os_id,
            correlationId: $model->correlation_id,
            estado: EstadoSaga::from($model->estado),
            orcamentoId: $model->orcamento_id,
            valorTotal: $model->valor_total,
            linkAprovacao: $model->link_aprovacao,
            pagamentoId: $model->pagamento_id,
            linkPagamento: $model->link_pagamento,
            execucaoId: $model->execucao_id,
            estoqueBaixado: $model->estoque_baixado,
            compensacoesPendentes: array_map(fn (string $c) => Compensacao::from($c), $model->compensacoes_pendentes ?? []),
            tentativasCompensacao: $model->tentativas_compensacao,
            motivoFalha: $model->motivo_falha,
            ultimoComandoId: $model->ultimo_comando_id,
        );
    }
}
