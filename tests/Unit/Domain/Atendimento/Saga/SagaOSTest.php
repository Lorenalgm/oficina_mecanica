<?php

namespace Tests\Unit\Domain\Atendimento\Saga;

use Domain\Atendimento\Saga\Compensacao;
use Domain\Atendimento\Saga\EstadoSaga;
use Domain\Atendimento\Saga\SagaOS;
use Domain\Atendimento\Saga\TransicaoInvalida;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SagaOSTest extends TestCase
{
    private function nova(): SagaOS
    {
        return SagaOS::iniciar('5d2a3c4e-1f2b-4c3d-9e8f-0a1b2c3d4e5f', 42, 'corr-1');
    }

    private function ate(string $etapa): SagaOS
    {
        $saga = $this->nova();
        $passos = [
            'aprovacao' => fn () => $saga->orcamentoGerado(7, 15000, 'https://billing/aprovar/7'),
            'estoque' => fn () => $saga->orcamentoAprovado(),
            'pagamento' => fn () => $saga->estoqueBaixadoComSucesso(),
            'execucao' => fn () => $saga->pagamentoConfirmado(9),
            'concluida' => fn () => $saga->execucaoEnfileirada('exec-1'),
        ];
        foreach ($passos as $nome => $passo) {
            $passo();
            if ($nome === $etapa) {
                break;
            }
        }

        return $saga;
    }

    private function compensarTudo(SagaOS $saga): array
    {
        $executadas = [];
        while ($compensacao = $saga->proximaCompensacao()) {
            $executadas[] = $compensacao;
            $saga->compensacaoConcluida($compensacao);
        }

        return $executadas;
    }

    public function test_nasce_aguardando_orcamento_e_ativa(): void
    {
        $saga = $this->nova();

        $this->assertSame(EstadoSaga::AguardandoOrcamento, $saga->estado());
        $this->assertTrue($saga->ativa());
        $this->assertSame(42, $saga->osId);
        $this->assertSame('corr-1', $saga->correlationId);
        $this->assertSame([], $saga->compensacoesPendentes());
    }

    public function test_caminho_feliz_ate_a_conclusao(): void
    {
        $saga = $this->nova();

        $saga->orcamentoGerado(7, 15000, 'https://billing/aprovar/7');
        $this->assertSame(EstadoSaga::AguardandoAprovacao, $saga->estado());
        $this->assertSame(7, $saga->orcamentoId());
        $this->assertSame(15000, $saga->valorTotal());
        $this->assertSame('https://billing/aprovar/7', $saga->linkAprovacao());

        $saga->orcamentoAprovado();
        $this->assertSame(EstadoSaga::ReservandoEstoque, $saga->estado());

        $saga->estoqueBaixadoComSucesso();
        $this->assertSame(EstadoSaga::AguardandoPagamento, $saga->estado());
        $this->assertTrue($saga->estoqueBaixado());

        $saga->cobrancaCriada(9, 'https://mp/pagar/9');
        $this->assertSame(EstadoSaga::AguardandoPagamento, $saga->estado());
        $this->assertSame('https://mp/pagar/9', $saga->linkPagamento());

        $saga->pagamentoConfirmado(9);
        $this->assertSame(EstadoSaga::AguardandoExecucao, $saga->estado());
        $this->assertSame(9, $saga->pagamentoId());

        $saga->execucaoEnfileirada('exec-1');
        $this->assertSame(EstadoSaga::Concluida, $saga->estado());
        $this->assertSame('exec-1', $saga->execucaoId());
        $this->assertFalse($saga->ativa());
    }

    public static function falhas(): array
    {
        return [
            'orçamento falhou' => [null, [Compensacao::CancelarOS]],
            'estoque insuficiente' => ['estoque', [Compensacao::CancelarOrcamento, Compensacao::CancelarOS]],
            'pagamento recusado' => ['pagamento', [
                Compensacao::DevolverEstoque, Compensacao::CancelarOrcamento, Compensacao::CancelarOS,
            ]],
            'fila cheia na execução' => ['execucao', [
                Compensacao::EstornarPagamento, Compensacao::DevolverEstoque,
                Compensacao::CancelarOrcamento, Compensacao::CancelarOS,
            ]],
        ];
    }

    #[DataProvider('falhas')]
    public function test_falha_compensa_em_ordem_reversa(?string $etapa, array $esperadas): void
    {
        $saga = $etapa === null ? $this->nova() : $this->ate($etapa);

        $saga->falhar('motivo do teste');

        $this->assertSame(EstadoSaga::Compensando, $saga->estado());
        $this->assertSame('motivo do teste', $saga->motivoFalha());
        $this->assertSame($esperadas, $saga->compensacoesPendentes());
        $this->assertSame($esperadas, $this->compensarTudo($saga));
        $this->assertSame(EstadoSaga::Compensada, $saga->estado());
        $this->assertFalse($saga->estoqueBaixado());
        $this->assertFalse($saga->ativa());
    }

    public function test_recusa_do_cliente_so_cancela_a_os(): void
    {
        $saga = $this->ate('aprovacao');

        $saga->orcamentoRecusado();

        $this->assertSame([Compensacao::CancelarOS], $saga->compensacoesPendentes());
        $this->assertSame('Orçamento recusado pelo cliente.', $saga->motivoFalha());
    }

    public function test_compensacao_remota_falhando_ate_o_limite_trava_a_saga(): void
    {
        $saga = $this->ate('execucao');
        $saga->falhar('fila cheia');

        $this->assertTrue($saga->compensacaoFalhou(Compensacao::EstornarPagamento, 3));
        $this->assertTrue($saga->compensacaoFalhou(Compensacao::EstornarPagamento, 3));
        $this->assertSame(2, $saga->tentativasCompensacao());
        $this->assertFalse($saga->compensacaoFalhou(Compensacao::EstornarPagamento, 3));

        $this->assertSame(EstadoSaga::CompensacaoFalhou, $saga->estado());
        $this->assertNull($saga->proximaCompensacao());
        $this->assertFalse($saga->ativa());
    }

    public function test_compensacao_concluida_zera_as_tentativas(): void
    {
        $saga = $this->ate('execucao');
        $saga->falhar('fila cheia');
        $saga->compensacaoFalhou(Compensacao::EstornarPagamento, 3);

        $saga->compensacaoConcluida(Compensacao::EstornarPagamento);

        $this->assertSame(0, $saga->tentativasCompensacao());
        $this->assertSame(Compensacao::DevolverEstoque, $saga->proximaCompensacao());
    }

    public function test_nao_conclui_compensacao_fora_de_ordem(): void
    {
        $saga = $this->ate('execucao');
        $saga->falhar('fila cheia');

        $this->expectException(TransicaoInvalida::class);

        $saga->compensacaoConcluida(Compensacao::CancelarOS);
    }

    public function test_nao_registra_falha_de_compensacao_fora_de_ordem(): void
    {
        $saga = $this->ate('execucao');
        $saga->falhar('fila cheia');

        $this->expectException(TransicaoInvalida::class);

        $saga->compensacaoFalhou(Compensacao::CancelarOrcamento, 3);
    }

    public function test_sem_compensacao_quando_nao_esta_compensando(): void
    {
        $this->assertNull($this->ate('pagamento')->proximaCompensacao());
    }

    public static function transicoesInvalidas(): array
    {
        return [
            'aprovar antes do orçamento' => [null, fn (SagaOS $s) => $s->orcamentoAprovado()],
            'baixar estoque sem aprovação' => ['aprovacao', fn (SagaOS $s) => $s->estoqueBaixadoComSucesso()],
            'cobrança antes da baixa' => ['estoque', fn (SagaOS $s) => $s->cobrancaCriada(1, 'x')],
            'pagamento sem cobrança' => ['aprovacao', fn (SagaOS $s) => $s->pagamentoConfirmado(1)],
            'enfileirar sem pagamento' => ['pagamento', fn (SagaOS $s) => $s->execucaoEnfileirada('e')],
            'orçamento gerado duas vezes' => ['aprovacao', fn (SagaOS $s) => $s->orcamentoGerado(1, 1, 'x')],
            'recusa depois da aprovação' => ['estoque', fn (SagaOS $s) => $s->orcamentoRecusado()],
            'falha depois de concluída' => ['concluida', fn (SagaOS $s) => $s->falhar('tarde demais')],
        ];
    }

    #[DataProvider('transicoesInvalidas')]
    public function test_rejeita_transicao_fora_de_ordem(?string $etapa, callable $acao): void
    {
        $saga = $etapa === null ? $this->nova() : $this->ate($etapa);
        $estadoAntes = $saga->estado();

        try {
            $acao($saga);
            $this->fail('Era esperada TransicaoInvalida.');
        } catch (TransicaoInvalida $e) {
            $this->assertStringContainsString($estadoAntes->value, $e->getMessage());
        }

        $this->assertSame($estadoAntes, $saga->estado());
    }

    public function test_falha_durante_compensacao_e_invalida(): void
    {
        $saga = $this->nova();
        $saga->falhar('primeira');

        $this->expectException(TransicaoInvalida::class);

        $saga->falhar('segunda');
    }

    public function test_registra_o_ultimo_comando_enviado(): void
    {
        $saga = $this->nova();

        $saga->registrarComando('msg-1');

        $this->assertSame('msg-1', $saga->ultimoComandoId());
    }

    public static function statusPorEstado(): array
    {
        return [
            [EstadoSaga::AguardandoOrcamento, 'Em diagnóstico'],
            [EstadoSaga::AguardandoAprovacao, 'Aguardando aprovação'],
            [EstadoSaga::ReservandoEstoque, 'Aguardando pagamento'],
            [EstadoSaga::AguardandoPagamento, 'Aguardando pagamento'],
            [EstadoSaga::AguardandoExecucao, 'Em execução'],
            [EstadoSaga::Concluida, 'Em execução'],
            [EstadoSaga::Compensando, null],
            [EstadoSaga::Compensada, 'Cancelada'],
            [EstadoSaga::CompensacaoFalhou, null],
        ];
    }

    #[DataProvider('statusPorEstado')]
    public function test_estado_da_saga_define_o_status_da_os(EstadoSaga $estado, ?string $status): void
    {
        $this->assertSame($status, $estado->statusDaOS());
    }

    public function test_compensacoes_remotas_sao_as_que_dependem_de_outro_servico(): void
    {
        $this->assertTrue(Compensacao::EstornarPagamento->remota());
        $this->assertTrue(Compensacao::CancelarOrcamento->remota());
        $this->assertFalse(Compensacao::DevolverEstoque->remota());
        $this->assertFalse(Compensacao::CancelarOS->remota());
    }
}
