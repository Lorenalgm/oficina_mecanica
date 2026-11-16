<?php

namespace Tests\Feature\Saga;

use Domain\Shared\Messaging\Envelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Infrastructure\Messaging\ProcessadorDeMensagens;
use Tests\Concerns\AutenticaComJwt;
use Tests\Concerns\CriaOSParaSaga;
use Tests\TestCase;

class ConsultarSagaTest extends TestCase
{
    use RefreshDatabase, AutenticaComJwt, CriaOSParaSaga;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepararCatalogo();
    }

    public function test_mostra_estado_compensacoes_e_linha_do_tempo(): void
    {
        $os = $this->criarOSComServico();
        $sagaId = $this->postJson("/api/os/{$os->id}/orcamento", [], $this->cabecalhoJwt() + ['X-Request-Id' => 'corr-consulta'])
            ->json('data.saga_id');
        $processador = app(ProcessadorDeMensagens::class);
        foreach ([
            ['billing.evt.orcamento-gerado', ['orcamento_id' => 7, 'valor_total' => 19980, 'link_aprovacao' => 'https://billing/7']],
            ['billing.evt.orcamento-aprovado', ['orcamento_id' => 7]],
            ['billing.evt.pagamento-recusado', ['pagamento_id' => 9, 'motivo' => 'cartão recusado']],
        ] as [$type, $payload]) {
            $processador->processar(Envelope::criar($type, $sagaId, 'corr-consulta', $os->id, $payload)->paraJson(), 0);
        }

        $this->getJson("/api/os/{$os->id}/saga", $this->cabecalhoJwt())
            ->assertOk()
            ->assertJsonPath('data.saga_id', $sagaId)
            ->assertJsonPath('data.correlation_id', 'corr-consulta')
            ->assertJsonPath('data.estado', 'compensando')
            ->assertJsonPath('data.ativa', true)
            ->assertJsonPath('data.orcamento.valor_total', 19980)
            ->assertJsonPath('data.orcamento.link_aprovacao', 'https://billing/7')
            ->assertJsonPath('data.estoque_baixado', false)
            ->assertJsonPath('data.motivo_falha', 'Pagamento recusado: cartão recusado')
            ->assertJsonPath('data.compensacoes_pendentes', ['cancelar_orcamento', 'cancelar_os'])
            ->assertJsonPath('data.passos.*.passo', [
                'billing.cmd.gerar-orcamento',
                'billing.evt.orcamento-gerado',
                'billing.evt.orcamento-aprovado',
                'billing.cmd.cobrar',
                'billing.evt.pagamento-recusado',
                'compensacao.devolver_estoque',
                'billing.cmd.cancelar-orcamento',
            ])
            ->assertJsonPath('data.passos.0.detalhes.valor_total', 19980);
    }

    public function test_os_sem_saga_retorna_404(): void
    {
        $os = $this->criarOSComServico();

        $this->getJson("/api/os/{$os->id}/saga", $this->cabecalhoJwt())->assertNotFound();
    }

    public function test_exige_autenticacao(): void
    {
        $os = $this->criarOSComServico();

        $this->getJson("/api/os/{$os->id}/saga")->assertUnauthorized();
    }
}
