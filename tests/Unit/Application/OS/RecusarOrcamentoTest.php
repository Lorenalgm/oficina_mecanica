<?php

namespace Tests\Unit\Application\OS;

use App\Models\Cliente;
use App\Models\OS;
use App\Models\OSOrcamento;
use App\Models\Status;
use App\Models\Veiculo;
use Application\OS\UseCases\RecusarOrcamento;
use Database\Seeders\StatusSeeder;
use Domain\Atendimento\Exceptions\TokenAprovacaoInvalido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecusarOrcamentoTest extends TestCase
{
    use RefreshDatabase;

    private RecusarOrcamento $useCase;
    private OS $os;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
        $this->useCase = app(RecusarOrcamento::class);

        $cliente = Cliente::factory()->create();
        $veiculo = Veiculo::factory()->create(['cliente_id' => $cliente->id]);
        $status = Status::where('nome', 'Recebida')->first();

        $this->os = OS::create([
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'status_atual_id' => $status->id,
            'descricao_problema' => 'Teste',
        ]);
    }

    private function criarOrcamento(?string $token = 'token-recusa'): void
    {
        OSOrcamento::create([
            'os_id' => $this->os->id,
            'valor_total' => 100.00,
            'data_orcamento' => now(),
            'status' => 'pendente',
            'approval_token' => $token,
        ]);
    }

    public function test_recusa_com_token_valido(): void
    {
        $this->criarOrcamento();

        $resultado = $this->useCase->executar($this->os->id, 'token-recusa');

        $this->assertEquals('recusado', $resultado->getStatus()->value);
        $this->assertNull(OSOrcamento::where('os_id', $this->os->id)->first()->approval_token);
    }

    public function test_token_ausente_e_rejeitado(): void
    {
        $this->criarOrcamento();

        $this->expectException(TokenAprovacaoInvalido::class);
        $this->useCase->executar($this->os->id, null);
    }

    public function test_token_invalido_e_rejeitado(): void
    {
        $this->criarOrcamento();

        $this->expectException(TokenAprovacaoInvalido::class);
        $this->useCase->executar($this->os->id, 'errado');
    }

    public function test_token_reusado_e_rejeitado(): void
    {
        $this->criarOrcamento();

        $this->useCase->executar($this->os->id, 'token-recusa');

        $this->expectException(TokenAprovacaoInvalido::class);
        $this->useCase->executar($this->os->id, 'token-recusa');
    }
}
