<?php

namespace Domain\Atendimento\Saga;

class SagaOS
{
    /**
     * @param list<Compensacao> $compensacoesPendentes
     */
    public function __construct(
        public readonly string $id,
        public readonly int $osId,
        public readonly string $correlationId,
        private EstadoSaga $estado = EstadoSaga::AguardandoOrcamento,
        private ?int $orcamentoId = null,
        private ?int $valorTotal = null,
        private ?string $linkAprovacao = null,
        private ?int $pagamentoId = null,
        private ?string $linkPagamento = null,
        private ?string $execucaoId = null,
        private bool $estoqueBaixado = false,
        private array $compensacoesPendentes = [],
        private int $tentativasCompensacao = 0,
        private ?string $motivoFalha = null,
        private ?string $ultimoComandoId = null,
    ) {}

    public static function iniciar(string $id, int $osId, string $correlationId): self
    {
        return new self($id, $osId, $correlationId);
    }

    public function estado(): EstadoSaga { return $this->estado; }
    public function orcamentoId(): ?int { return $this->orcamentoId; }
    public function valorTotal(): ?int { return $this->valorTotal; }
    public function linkAprovacao(): ?string { return $this->linkAprovacao; }
    public function pagamentoId(): ?int { return $this->pagamentoId; }
    public function linkPagamento(): ?string { return $this->linkPagamento; }
    public function execucaoId(): ?string { return $this->execucaoId; }
    public function estoqueBaixado(): bool { return $this->estoqueBaixado; }
    /** @return list<Compensacao> */
    public function compensacoesPendentes(): array { return $this->compensacoesPendentes; }
    public function tentativasCompensacao(): int { return $this->tentativasCompensacao; }
    public function motivoFalha(): ?string { return $this->motivoFalha; }
    public function ultimoComandoId(): ?string { return $this->ultimoComandoId; }

    public function ativa(): bool
    {
        return $this->estado->ativa();
    }

    public function orcamentoGerado(int $orcamentoId, int $valorTotal, string $linkAprovacao): void
    {
        $this->exigir([EstadoSaga::AguardandoOrcamento], 'orcamento-gerado');
        $this->orcamentoId = $orcamentoId;
        $this->valorTotal = $valorTotal;
        $this->linkAprovacao = $linkAprovacao;
        $this->estado = EstadoSaga::AguardandoAprovacao;
    }

    public function orcamentoAprovado(): void
    {
        $this->exigir([EstadoSaga::AguardandoAprovacao], 'orcamento-aprovado');
        $this->estado = EstadoSaga::ReservandoEstoque;
    }

    public function estoqueBaixadoComSucesso(): void
    {
        $this->exigir([EstadoSaga::ReservandoEstoque], 'estoque baixado');
        $this->estoqueBaixado = true;
        $this->estado = EstadoSaga::AguardandoPagamento;
    }

    public function cobrancaCriada(int $pagamentoId, string $linkPagamento): void
    {
        $this->exigir([EstadoSaga::AguardandoPagamento], 'cobranca-criada');
        $this->pagamentoId = $pagamentoId;
        $this->linkPagamento = $linkPagamento;
    }

    public function pagamentoConfirmado(int $pagamentoId): void
    {
        $this->exigir([EstadoSaga::AguardandoPagamento], 'pagamento-confirmado');
        $this->pagamentoId = $pagamentoId;
        $this->estado = EstadoSaga::AguardandoExecucao;
    }

    public function execucaoEnfileirada(string $execucaoId): void
    {
        $this->exigir([EstadoSaga::AguardandoExecucao], 'execucao enfileirada');
        $this->execucaoId = $execucaoId;
        $this->estado = EstadoSaga::Concluida;
    }

    public function orcamentoRecusado(): void
    {
        $this->exigir([EstadoSaga::AguardandoAprovacao], 'orcamento-recusado');
        $this->iniciarCompensacao('Orçamento recusado pelo cliente.', [Compensacao::CancelarOS]);
    }

    /**
     * Falha de um passo: monta as compensações, em ordem reversa, do que já
     * foi feito até aqui.
     */
    public function falhar(string $motivo): void
    {
        $this->exigir([
            EstadoSaga::AguardandoOrcamento,
            EstadoSaga::AguardandoAprovacao,
            EstadoSaga::ReservandoEstoque,
            EstadoSaga::AguardandoPagamento,
            EstadoSaga::AguardandoExecucao,
        ], 'falha');

        $compensacoes = [];
        if ($this->estado === EstadoSaga::AguardandoExecucao) {
            $compensacoes[] = Compensacao::EstornarPagamento;
        }
        if ($this->estoqueBaixado) {
            $compensacoes[] = Compensacao::DevolverEstoque;
        }
        if ($this->orcamentoId !== null) {
            $compensacoes[] = Compensacao::CancelarOrcamento;
        }
        $compensacoes[] = Compensacao::CancelarOS;

        $this->iniciarCompensacao($motivo, $compensacoes);
    }

    public function proximaCompensacao(): ?Compensacao
    {
        return $this->estado === EstadoSaga::Compensando ? ($this->compensacoesPendentes[0] ?? null) : null;
    }

    public function compensacaoConcluida(Compensacao $compensacao): void
    {
        $this->exigirCompensacaoAtual($compensacao);

        array_shift($this->compensacoesPendentes);
        $this->tentativasCompensacao = 0;

        if ($compensacao === Compensacao::DevolverEstoque) {
            $this->estoqueBaixado = false;
        }
        if ($this->compensacoesPendentes === []) {
            $this->estado = EstadoSaga::Compensada;
        }
    }

    /**
     * @return bool true se ainda vale reenviar a compensação
     */
    public function compensacaoFalhou(Compensacao $compensacao, int $maxTentativas): bool
    {
        $this->exigirCompensacaoAtual($compensacao);

        $this->tentativasCompensacao++;
        if ($this->tentativasCompensacao >= $maxTentativas) {
            $this->estado = EstadoSaga::CompensacaoFalhou;

            return false;
        }

        return true;
    }

    public function registrarComando(string $messageId): void
    {
        $this->ultimoComandoId = $messageId;
    }

    /** @param list<Compensacao> $compensacoes */
    private function iniciarCompensacao(string $motivo, array $compensacoes): void
    {
        $this->motivoFalha = $motivo;
        $this->compensacoesPendentes = $compensacoes;
        $this->tentativasCompensacao = 0;
        $this->estado = EstadoSaga::Compensando;
    }

    private function exigirCompensacaoAtual(Compensacao $compensacao): void
    {
        if ($this->proximaCompensacao() !== $compensacao) {
            throw TransicaoInvalida::de($this->estado, "conclusão de {$compensacao->value}");
        }
    }

    /** @param list<EstadoSaga> $permitidos */
    private function exigir(array $permitidos, string $evento): void
    {
        if (! in_array($this->estado, $permitidos, true)) {
            throw TransicaoInvalida::de($this->estado, $evento);
        }
    }
}
