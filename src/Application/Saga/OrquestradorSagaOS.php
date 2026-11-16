<?php

namespace Application\Saga;

use Domain\Atendimento\Repositories\OSRepository;
use Domain\Atendimento\Saga\Compensacao;
use Domain\Atendimento\Saga\EstadoSaga;
use Domain\Atendimento\Saga\NotificadorCliente;
use Domain\Atendimento\Saga\SagaOS;
use Domain\Atendimento\Saga\SagaRepository;
use Domain\Atendimento\Saga\TransicaoInvalida;
use Domain\Catalogo\Exceptions\EstoqueInsuficienteException;
use Domain\Catalogo\Repositories\InsumoRepository;
use Domain\Identidade\Repositories\ClienteRepository;
use Domain\Shared\Messaging\Envelope;
use Domain\Shared\Messaging\MessageHandler;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrquestradorSagaOS implements MessageHandler
{
    public const EVENTOS = [
        'billing.evt.orcamento-gerado',
        'billing.evt.orcamento-falhou',
        'billing.evt.orcamento-aprovado',
        'billing.evt.orcamento-recusado',
        'billing.evt.orcamento-cancelado',
        'billing.evt.cobranca-criada',
        'billing.evt.pagamento-confirmado',
        'billing.evt.pagamento-recusado',
        'billing.evt.pagamento-estornado',
        'billing.evt.estorno-falhou',
        'execucao.evt.enfileirada',
        'execucao.evt.rejeitada',
        'execucao.evt.status-alterado',
        'execucao.evt.finalizada',
    ];

    public function __construct(
        private SagaRepository $sagas,
        private ComandosDaSaga $comandos,
        private SincronizadorDeStatus $status,
        private OSRepository $ordens,
        private InsumoRepository $insumos,
        private ClienteRepository $clientes,
        private NotificadorCliente $notificador,
        private int $maxTentativasCompensacao = 3,
    ) {}

    public function tratar(Envelope $envelope): void
    {
        $saga = $this->sagas->buscar($envelope->sagaId);
        if ($saga === null) {
            Log::warning('Evento de Saga desconhecida ignorado', $this->contexto($envelope) + [
                'event_type' => 'saga.desconhecida',
            ]);

            return;
        }

        $this->sagas->registrarPasso($saga->id, $envelope->type, $envelope->payload);
        $estadoAnterior = $saga->estado();

        try {
            $sincronizarStatus = $this->aplicar($saga, $envelope);
        } catch (TransicaoInvalida $e) {
            Log::warning('Evento fora de ordem ignorado', $this->contexto($envelope) + [
                'event_type' => 'saga.evento_fora_de_ordem',
                'estado' => $saga->estado()->value,
                'erro' => $e->getMessage(),
            ]);

            return;
        }

        $this->sagas->salvar($saga);
        if ($sincronizarStatus) {
            $this->status->aplicar($saga);
        }
        $this->registrarMudanca($saga, $estadoAnterior, $envelope);
    }

    private function aplicar(SagaOS $saga, Envelope $envelope): bool
    {
        $p = $envelope->payload;

        match ($envelope->type) {
            'billing.evt.orcamento-gerado' => $this->orcamentoGerado($saga, $p),
            'billing.evt.orcamento-falhou' => $this->falhar($saga, 'Falha ao gerar orçamento: '.($p['motivo'] ?? 'sem motivo')),
            'billing.evt.orcamento-aprovado' => $this->orcamentoAprovado($saga),
            'billing.evt.orcamento-recusado' => $this->orcamentoRecusado($saga),
            'billing.evt.cobranca-criada' => $this->cobrancaCriada($saga, $p),
            'billing.evt.pagamento-confirmado' => $this->pagamentoConfirmado($saga, $p),
            'billing.evt.pagamento-recusado' => $this->falhar($saga, 'Pagamento recusado: '.($p['motivo'] ?? 'sem motivo')),
            'execucao.evt.enfileirada' => $saga->execucaoEnfileirada((string) $p['execucao_id']),
            'execucao.evt.rejeitada' => $this->falhar($saga, 'Execução rejeitada: '.($p['motivo'] ?? 'sem motivo')),
            'billing.evt.orcamento-cancelado' => $this->compensacaoConfirmada($saga, Compensacao::CancelarOrcamento),
            'billing.evt.pagamento-estornado' => $this->compensacaoConfirmada($saga, Compensacao::EstornarPagamento),
            'billing.evt.estorno-falhou' => $this->estornoFalhou($saga, $p),
            'execucao.evt.status-alterado' => $this->exigirConcluida($saga, $envelope->type),
            'execucao.evt.finalizada' => $this->finalizada($saga, $envelope->type),
        };

        return ! in_array($envelope->type, ['execucao.evt.status-alterado', 'execucao.evt.finalizada'], true);
    }

    private function orcamentoGerado(SagaOS $saga, array $p): void
    {
        $saga->orcamentoGerado((int) $p['orcamento_id'], (int) $p['valor_total'], (string) $p['link_aprovacao']);
        $this->notificador->enviarLinkDeAprovacao($saga->osId, $saga->linkAprovacao(), $saga->valorTotal());
    }

    private function orcamentoAprovado(SagaOS $saga): void
    {
        $saga->orcamentoAprovado();

        try {
            $this->baixarEstoque($saga->osId);
        } catch (EstoqueInsuficienteException $e) {
            $this->falhar($saga, $e->getMessage());

            return;
        }

        $saga->estoqueBaixadoComSucesso();
        $cliente = $this->clientes->findById($this->ordens->findById($saga->osId)->getClienteId());
        $this->comandos->enviar($saga, 'billing.cmd.cobrar', [
            'orcamento_id' => $saga->orcamentoId(),
            'valor' => $saga->valorTotal(),
            'pagador' => [
                'nome' => $cliente?->getNome(),
                'email' => $cliente?->getEmail(),
                'documento' => $cliente?->getDocumento()->getValue(),
            ],
        ]);
    }

    private function orcamentoRecusado(SagaOS $saga): void
    {
        $saga->orcamentoRecusado();
        $this->compensar($saga);
    }

    private function cobrancaCriada(SagaOS $saga, array $p): void
    {
        $saga->cobrancaCriada((int) $p['pagamento_id'], (string) $p['link_pagamento']);
        $this->notificador->enviarLinkDePagamento($saga->osId, $saga->linkPagamento(), $saga->valorTotal());
    }

    private function pagamentoConfirmado(SagaOS $saga, array $p): void
    {
        $saga->pagamentoConfirmado((int) $p['pagamento_id']);

        $os = $this->ordens->findById($saga->osId);
        $this->comandos->enviar($saga, 'execucao.cmd.enfileirar', [
            'filial' => $os->getFilial(),
            'veiculo' => ['placa' => $os->getVeiculoPlaca()],
            'servicos' => array_map(
                fn ($servico) => ['descricao' => $servico->getServicoNome()],
                $os->getServicos() ?? [],
            ),
        ]);
    }

    private function falhar(SagaOS $saga, string $motivo): void
    {
        $saga->falhar($motivo);
        $this->compensar($saga);
    }

    private function compensacaoConfirmada(SagaOS $saga, Compensacao $compensacao): void
    {
        $saga->compensacaoConcluida($compensacao);
        $this->compensar($saga);
    }

    private function estornoFalhou(SagaOS $saga, array $p): void
    {
        if ($saga->compensacaoFalhou(Compensacao::EstornarPagamento, $this->maxTentativasCompensacao)) {
            $this->compensar($saga);

            return;
        }

        Log::error('Compensação da Saga esgotou as tentativas', [
            'event_type' => 'saga.compensacao_falhou',
            'saga_id' => $saga->id,
            'os_id' => $saga->osId,
            'compensacao' => Compensacao::EstornarPagamento->value,
            'motivo' => $p['motivo'] ?? null,
        ]);
    }

    private function exigirConcluida(SagaOS $saga, string $evento): void
    {
        if ($saga->estado() !== EstadoSaga::Concluida) {
            throw TransicaoInvalida::de($saga->estado(), $evento);
        }
    }

    private function finalizada(SagaOS $saga, string $evento): void
    {
        $this->exigirConcluida($saga, $evento);
        $this->status->definir($saga->osId, 'Finalizada');
    }

    private function compensar(SagaOS $saga): void
    {
        while ($compensacao = $saga->proximaCompensacao()) {
            switch ($compensacao) {
                case Compensacao::EstornarPagamento:
                    $this->comandos->enviar($saga, 'billing.cmd.estornar', [
                        'pagamento_id' => $saga->pagamentoId(),
                        'motivo' => $saga->motivoFalha(),
                    ]);

                    return;
                case Compensacao::CancelarOrcamento:
                    $this->comandos->enviar($saga, 'billing.cmd.cancelar-orcamento', [
                        'orcamento_id' => $saga->orcamentoId(),
                        'motivo' => $saga->motivoFalha(),
                    ]);

                    return;
                case Compensacao::DevolverEstoque:
                    $this->devolverEstoque($saga->osId);
                    break;
                case Compensacao::CancelarOS:
                    break;
            }

            $this->sagas->registrarPasso($saga->id, "compensacao.{$compensacao->value}");
            $saga->compensacaoConcluida($compensacao);
        }
    }

    private function baixarEstoque(int $osId): void
    {
        $atualizados = [];
        foreach ($this->ordens->findById($osId)->insumosConsumidos() as $insumoId => $quantidade) {
            $insumo = $this->insumos->findById($insumoId)
                ?? throw new RuntimeException("Insumo #{$insumoId} não encontrado.");
            $insumo->darBaixa($quantidade);
            $atualizados[] = $insumo;
        }
        foreach ($atualizados as $insumo) {
            $this->insumos->save($insumo);
        }
    }

    private function devolverEstoque(int $osId): void
    {
        foreach ($this->ordens->findById($osId)->insumosConsumidos() as $insumoId => $quantidade) {
            $insumo = $this->insumos->findById($insumoId)
                ?? throw new RuntimeException("Insumo #{$insumoId} não encontrado.");
            $insumo->devolver($quantidade);
            $this->insumos->save($insumo);
        }
    }

    private function registrarMudanca(SagaOS $saga, EstadoSaga $anterior, Envelope $envelope): void
    {
        if ($saga->estado() === $anterior) {
            return;
        }

        Log::info('Saga mudou de estado', $this->contexto($envelope) + [
            'event_type' => 'saga.transicao',
            'estado_anterior' => $anterior->value,
            'estado' => $saga->estado()->value,
            'motivo_falha' => $saga->motivoFalha(),
        ]);
    }

    private function contexto(Envelope $envelope): array
    {
        return [
            'saga_id' => $envelope->sagaId,
            'os_id' => $envelope->osId,
            'message_type' => $envelope->type,
        ];
    }
}
