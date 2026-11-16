<?php

namespace Tests\Feature\Saga;

use Domain\Shared\Messaging\Envelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Infrastructure\Messaging\Broker;
use Tests\Concerns\AutenticaComJwt;
use Tests\Concerns\CriaOSParaSaga;
use Tests\Support\FakeBroker;
use Tests\TestCase;

class ReenviarComandoDaSagaTest extends TestCase
{
    use RefreshDatabase, AutenticaComJwt, CriaOSParaSaga;

    public function test_republica_o_ultimo_comando_com_o_mesmo_message_id(): void
    {
        $broker = new FakeBroker();
        $this->app->instance(Broker::class, $broker);
        $this->prepararCatalogo();
        $os = $this->criarOSComServico();
        $sagaId = $this->postJson("/api/os/{$os->id}/orcamento", [], $this->cabecalhoJwt())->json('data.saga_id');
        $this->artisan('outbox:publicar')->assertSuccessful();

        $this->artisan('saga:reenviar', ['saga_id' => $sagaId])
            ->expectsOutputToContain('recolocado na outbox')
            ->assertSuccessful();
        $this->artisan('outbox:publicar')->assertSuccessful();

        $this->assertCount(2, $broker->publicadas);
        $this->assertSame($broker->publicadas[0]['corpo'], $broker->publicadas[1]['corpo']);
        $this->assertDatabaseHas('saga_passos', ['saga_id' => $sagaId, 'passo' => 'reenvio_manual']);
    }

    public function test_saga_inexistente_falha(): void
    {
        $this->artisan('saga:reenviar', ['saga_id' => Envelope::novoUuid()])
            ->expectsOutput('Saga não encontrada.')
            ->assertFailed();
    }

    public function test_saga_sem_comando_na_outbox_falha(): void
    {
        $this->prepararCatalogo();
        $os = $this->criarOSComServico();
        $sagaId = $this->postJson("/api/os/{$os->id}/orcamento", [], $this->cabecalhoJwt())->json('data.saga_id');
        DB::table('outbox')->delete();

        $this->artisan('saga:reenviar', ['saga_id' => $sagaId])
            ->expectsOutput('A Saga não tem comando para reenviar.')
            ->assertFailed();
    }
}
