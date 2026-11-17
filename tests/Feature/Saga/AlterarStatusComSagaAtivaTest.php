<?php

namespace Tests\Feature\Saga;

use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\AutenticaComJwt;
use Tests\Concerns\CriaOSParaSaga;
use Tests\TestCase;

class AlterarStatusComSagaAtivaTest extends TestCase
{
    use RefreshDatabase, AutenticaComJwt, CriaOSParaSaga;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepararCatalogo();
    }

    public function test_bloqueia_alteracao_manual_enquanto_a_saga_esta_ativa(): void
    {
        $os = $this->criarOSComServico();
        $sagaId = $this->postJson("/api/os/{$os->id}/orcamento", [], $this->cabecalhoJwt())->json('data.saga_id');

        $this->patchJson("/api/os/{$os->id}/status", [
            'status_id' => Status::where('nome', 'Em execução')->value('id'),
        ], $this->cabecalhoJwt())
            ->assertStatus(409)
            ->assertJsonPath('saga_id', $sagaId);

        $this->assertNotSame('Em execução', $this->statusDaOS($os->id));
    }

    public function test_permite_alteracao_manual_sem_saga(): void
    {
        $os = $this->criarOSComServico();

        $this->patchJson("/api/os/{$os->id}/status", [
            'status_id' => Status::where('nome', 'Em diagnóstico')->value('id'),
        ], $this->cabecalhoJwt())->assertOk();

        $this->assertSame('Em diagnóstico', $this->statusDaOS($os->id));
    }
}
