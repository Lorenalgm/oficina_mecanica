<?php

namespace Tests\Feature\Saga;

use App\Mail\LinkDaSaga;
use App\Models\Insumo;
use App\Models\OS;
use Domain\Shared\Messaging\Envelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Infrastructure\Messaging\Decisao;
use Infrastructure\Messaging\ProcessadorDeMensagens;
use Tests\Concerns\AutenticaComJwt;
use Tests\Concerns\CriaOSParaSaga;
use Tests\TestCase;

class OrquestradorSagaOSTest extends TestCase
{
    use RefreshDatabase, AutenticaComJwt, CriaOSParaSaga;

    private OS $os;
    private string $sagaId;
    private int $etapasAplicadas = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function iniciar(int $estoque = 10, int $quantidade = 2): void
    {
        $this->prepararCatalogo($estoque);
        $this->os = $this->criarOSComServico($quantidade, 'SP-CENTRO');
        $this->sagaId = $this->postJson("/api/os/{$this->os->id}/orcamento", [], $this->cabecalhoJwt())
            ->assertStatus(202)
            ->json('data.saga_id');
    }

    private function evento(string $type, array $payload = [], ?string $sagaId = null): Decisao
    {
        $envelope = Envelope::criar($type, $sagaId ?? $this->sagaId, 'corr-evento', $this->os->id, $payload);

        return app(ProcessadorDeMensagens::class)->processar($envelope->paraJson(), 0);
    }

    private function ate(string $etapa): void
    {
        $passos = [
            'gerado' => ['billing.evt.orcamento-gerado', ['orcamento_id' => 7, 'valor_total' => 19980, 'link_aprovacao' => 'https://billing/orcamentos/7/aprovar?token=abc']],
            'aprovado' => ['billing.evt.orcamento-aprovado', ['orcamento_id' => 7]],
            'cobranca' => ['billing.evt.cobranca-criada', ['pagamento_id' => 9, 'link_pagamento' => 'https://mp/checkout/9']],
            'pago' => ['billing.evt.pagamento-confirmado', ['pagamento_id' => 9, 'valor' => 19980]],
            'enfileirada' => ['execucao.evt.enfileirada', ['execucao_id' => 'exec-1', 'posicao_na_fila' => 1]],
        ];
        foreach (array_slice($passos, $this->etapasAplicadas, null, true) as $nome => [$type, $payload]) {
            $this->assertSame(Decisao::Confirmar, $this->evento($type, $payload));
            $this->etapasAplicadas++;
            if ($nome === $etapa) {
                return;
            }
        }
    }

    private function saga(): object
    {
        return DB::table('sagas')->where('id', $this->sagaId)->first();
    }

    /** @return list<Envelope> */
    private function comandos(): array
    {
        return DB::table('outbox')->orderBy('id')->pluck('corpo')
            ->map(fn (string $c) => Envelope::deJson($c))->all();
    }

    private function tiposDeComando(): array
    {
        return array_map(fn (Envelope $e) => $e->type, $this->comandos());
    }

    private function ultimoComando(): Envelope
    {
        $comandos = $this->comandos();

        return end($comandos);
    }

    private function estoque(): int
    {
        return Insumo::find($this->insumo->id)->quantidade_estoque;
    }

    public function test_caminho_feliz_ate_a_os_finalizada(): void
    {
        $this->iniciar();

        $this->ate('gerado');
        $this->assertSame('aguardando_aprovacao', $this->saga()->estado);
        $this->assertSame('Aguardando aprovação', $this->statusDaOS($this->os->id));
        Mail::assertSent(LinkDaSaga::class, fn (LinkDaSaga $m) => $m->hasTo('ana@exemplo.com')
            && $m->link === 'https://billing/orcamentos/7/aprovar?token=abc'
            && $m->valor === 'R$ 199,80');

        $this->ate('aprovado');
        $this->assertSame(8, $this->estoque());
        $this->assertSame('Aguardando pagamento', $this->statusDaOS($this->os->id));
        $cobrar = $this->ultimoComando();
        $this->assertSame('billing.cmd.cobrar', $cobrar->type);
        $this->assertSame([
            'orcamento_id' => 7,
            'valor' => 19980,
            'pagador' => ['nome' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'documento' => $this->cliente->documento],
        ], $cobrar->payload);

        $this->ate('cobranca');
        $this->assertSame('https://mp/checkout/9', $this->saga()->link_pagamento);
        Mail::assertSent(LinkDaSaga::class, fn (LinkDaSaga $m) => $m->link === 'https://mp/checkout/9');

        $this->ate('pago');
        $this->assertSame('Em execução', $this->statusDaOS($this->os->id));
        $enfileirar = $this->ultimoComando();
        $this->assertSame('execucao.cmd.enfileirar', $enfileirar->type);
        $this->assertSame([
            'filial' => 'SP-CENTRO',
            'veiculo' => ['placa' => 'ABC1D23'],
            'servicos' => [['descricao' => 'Troca de óleo']],
        ], $enfileirar->payload);

        $this->ate('enfileirada');
        $this->assertSame('concluida', $this->saga()->estado);
        $this->assertSame('exec-1', $this->saga()->execucao_id);

        $this->assertSame(Decisao::Confirmar, $this->evento('execucao.evt.status-alterado', ['status' => 'em_reparo']));
        $this->assertSame('Em execução', $this->statusDaOS($this->os->id));

        $this->assertSame(Decisao::Confirmar, $this->evento('execucao.evt.finalizada', ['execucao_id' => 'exec-1']));
        $this->assertSame('Finalizada', $this->statusDaOS($this->os->id));
        $this->assertSame('concluida', $this->saga()->estado);

        $this->assertSame(
            ['billing.cmd.gerar-orcamento', 'billing.cmd.cobrar', 'execucao.cmd.enfileirar'],
            $this->tiposDeComando(),
        );
        $this->assertSame(10, DB::table('saga_passos')->where('saga_id', $this->sagaId)->count());
    }

    public function test_comandos_seguem_com_o_correlation_id_da_requisicao_original(): void
    {
        $this->prepararCatalogo();
        $this->os = $this->criarOSComServico();
        $this->sagaId = $this->postJson("/api/os/{$this->os->id}/orcamento", [], $this->cabecalhoJwt() + ['X-Request-Id' => 'corr-original'])
            ->json('data.saga_id');

        $this->ate('aprovado');

        $this->assertSame(['corr-original', 'corr-original'], array_map(fn (Envelope $e) => $e->correlationId, $this->comandos()));
    }

    public function test_orcamento_falhou_cancela_a_os(): void
    {
        $this->iniciar();

        $this->evento('billing.evt.orcamento-falhou', ['motivo' => 'serviço inativo']);

        $this->assertSame('compensada', $this->saga()->estado);
        $this->assertSame('Falha ao gerar orçamento: serviço inativo', $this->saga()->motivo_falha);
        $this->assertSame('Cancelada', $this->statusDaOS($this->os->id));
        $this->assertSame(['billing.cmd.gerar-orcamento'], $this->tiposDeComando());
    }

    public function test_recusa_do_cliente_cancela_a_os_sem_mexer_no_billing(): void
    {
        $this->iniciar();
        $this->ate('gerado');

        $this->evento('billing.evt.orcamento-recusado', ['orcamento_id' => 7]);

        $this->assertSame('compensada', $this->saga()->estado);
        $this->assertSame('Cancelada', $this->statusDaOS($this->os->id));
        $this->assertSame(10, $this->estoque());
        $this->assertSame(['billing.cmd.gerar-orcamento'], $this->tiposDeComando());
    }

    public function test_estoque_insuficiente_cancela_orcamento_e_os(): void
    {
        $this->iniciar(estoque: 1, quantidade: 2);
        $this->ate('gerado');

        $this->evento('billing.evt.orcamento-aprovado', ['orcamento_id' => 7]);

        $this->assertSame(1, $this->estoque());
        $this->assertSame('compensando', $this->saga()->estado);
        $this->assertStringContainsString('Óleo 5W30', $this->saga()->motivo_falha);
        $cancelar = $this->ultimoComando();
        $this->assertSame('billing.cmd.cancelar-orcamento', $cancelar->type);
        $this->assertSame(7, $cancelar->payload['orcamento_id']);

        $this->evento('billing.evt.orcamento-cancelado', ['orcamento_id' => 7]);

        $this->assertSame('compensada', $this->saga()->estado);
        $this->assertSame('Cancelada', $this->statusDaOS($this->os->id));
    }

    public function test_pagamento_recusado_devolve_estoque_e_cancela(): void
    {
        $this->iniciar();
        $this->ate('cobranca');
        $this->assertSame(8, $this->estoque());

        $this->evento('billing.evt.pagamento-recusado', ['pagamento_id' => 9, 'motivo' => 'cartão recusado']);

        $this->assertSame(10, $this->estoque());
        $this->assertSame('billing.cmd.cancelar-orcamento', $this->ultimoComando()->type);
        $this->assertSame('Aguardando pagamento', $this->statusDaOS($this->os->id));

        $this->evento('billing.evt.orcamento-cancelado', ['orcamento_id' => 7]);

        $this->assertSame('compensada', $this->saga()->estado);
        $this->assertSame('Cancelada', $this->statusDaOS($this->os->id));
        $this->assertSame(
            ['compensacao.devolver_estoque', 'compensacao.cancelar_os'],
            DB::table('saga_passos')->where('saga_id', $this->sagaId)->where('passo', 'like', 'compensacao.%')->orderBy('id')->pluck('passo')->all(),
        );
    }

    public function test_fila_cheia_estorna_devolve_estoque_e_cancela(): void
    {
        $this->iniciar();
        $this->ate('pago');

        $this->evento('execucao.evt.rejeitada', ['motivo' => 'capacidade_esgotada']);

        $estornar = $this->ultimoComando();
        $this->assertSame('billing.cmd.estornar', $estornar->type);
        $this->assertSame(['pagamento_id' => 9, 'motivo' => 'Execução rejeitada: capacidade_esgotada'], $estornar->payload);
        $this->assertSame(8, $this->estoque());

        $this->evento('billing.evt.pagamento-estornado', ['pagamento_id' => 9]);

        $this->assertSame(10, $this->estoque());
        $this->assertSame('billing.cmd.cancelar-orcamento', $this->ultimoComando()->type);

        $this->evento('billing.evt.orcamento-cancelado', ['orcamento_id' => 7]);

        $this->assertSame('compensada', $this->saga()->estado);
        $this->assertSame('Cancelada', $this->statusDaOS($this->os->id));
        $this->assertSame(
            ['billing.cmd.gerar-orcamento', 'billing.cmd.cobrar', 'execucao.cmd.enfileirar', 'billing.cmd.estornar', 'billing.cmd.cancelar-orcamento'],
            $this->tiposDeComando(),
        );
    }

    public function test_estorno_falhando_e_reenviado_ate_o_limite(): void
    {
        Log::spy();
        $this->iniciar();
        $this->ate('pago');
        $this->evento('execucao.evt.rejeitada', ['motivo' => 'capacidade_esgotada']);

        $this->evento('billing.evt.estorno-falhou', ['pagamento_id' => 9, 'motivo' => 'Mercado Pago fora']);
        $this->evento('billing.evt.estorno-falhou', ['pagamento_id' => 9, 'motivo' => 'Mercado Pago fora']);
        $this->assertSame('compensando', $this->saga()->estado);
        $this->evento('billing.evt.estorno-falhou', ['pagamento_id' => 9, 'motivo' => 'Mercado Pago fora']);

        $this->assertSame('compensacao_falhou', $this->saga()->estado);
        $this->assertSame(3, collect($this->tiposDeComando())->filter(fn ($t) => $t === 'billing.cmd.estornar')->count());
        $this->assertSame('Em execução', $this->statusDaOS($this->os->id));
        $this->assertSame(8, $this->estoque());
        Log::shouldHaveReceived('error')->withArgs(
            fn ($mensagem, $contexto = []) => ($contexto['event_type'] ?? null) === 'saga.compensacao_falhou'
                && $contexto['saga_id'] === $this->sagaId
                && $contexto['motivo'] === 'Mercado Pago fora'
        )->once();
    }

    public function test_evento_fora_de_ordem_e_ignorado_sem_erro(): void
    {
        Log::spy();
        $this->iniciar();

        $decisao = $this->evento('billing.evt.pagamento-confirmado', ['pagamento_id' => 9]);

        $this->assertSame(Decisao::Confirmar, $decisao);
        $this->assertSame('aguardando_orcamento', $this->saga()->estado);
        $this->assertSame(['billing.cmd.gerar-orcamento'], $this->tiposDeComando());
        Log::shouldHaveReceived('warning')->withArgs(
            fn ($mensagem, $contexto = []) => ($contexto['event_type'] ?? null) === 'saga.evento_fora_de_ordem'
        )->once();
    }

    public function test_eventos_da_execucao_antes_da_conclusao_sao_ignorados(): void
    {
        $this->iniciar();
        $this->ate('cobranca');

        $this->evento('execucao.evt.finalizada', ['execucao_id' => 'exec-1']);

        $this->assertSame('Aguardando pagamento', $this->statusDaOS($this->os->id));
    }

    public function test_evento_de_saga_desconhecida_e_ignorado(): void
    {
        Log::spy();
        $this->iniciar();

        $decisao = $this->evento('billing.evt.orcamento-gerado', ['orcamento_id' => 1], Envelope::novoUuid());

        $this->assertSame(Decisao::Confirmar, $decisao);
        $this->assertSame('aguardando_orcamento', $this->saga()->estado);
        Log::shouldHaveReceived('warning')->withArgs(
            fn ($mensagem, $contexto = []) => ($contexto['event_type'] ?? null) === 'saga.desconhecida'
        )->once();
    }

    public function test_transicao_gera_log_estruturado_para_o_painel(): void
    {
        Log::spy();
        $this->iniciar();

        $this->ate('gerado');

        Log::shouldHaveReceived('info')->withArgs(
            fn ($mensagem, $contexto = []) => ($contexto['event_type'] ?? null) === 'saga.transicao'
                && $contexto['estado_anterior'] === 'aguardando_orcamento'
                && $contexto['estado'] === 'aguardando_aprovacao'
                && $contexto['saga_id'] === $this->sagaId
        )->once();
    }
}
