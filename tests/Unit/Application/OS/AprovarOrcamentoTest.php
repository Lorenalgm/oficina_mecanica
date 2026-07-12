<?php

namespace Tests\Unit\Application\OS;

use App\Models\Insumo;
use App\Models\OS;
use App\Models\OSOrcamento;
use App\Models\OSServico;
use App\Models\OSServicoInsumo;
use App\Models\Status;
use App\Models\Cliente;
use App\Models\Veiculo;
use Application\OS\UseCases\AprovarOrcamento;
use Database\Seeders\StatusSeeder;
use Domain\Atendimento\Exceptions\TokenAprovacaoInvalido;
use Domain\Catalogo\Exceptions\EstoqueInsuficienteException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AprovarOrcamentoTest extends TestCase
{
    use RefreshDatabase;

    private AprovarOrcamento $useCase;
    private OS $os;
    private Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
        $this->useCase = app(AprovarOrcamento::class);

        $cliente = Cliente::factory()->create();
        $veiculo = Veiculo::factory()->create(['cliente_id' => $cliente->id]);
        $status = Status::where('nome', 'Recebida')->first();
        $servico = \App\Models\Servico::factory()->create(['valor' => 100.00]);
        $this->insumo = Insumo::factory()->create(['valor' => 50.00, 'quantidade_estoque' => 10]);

        $this->os = OS::create([
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'status_atual_id' => $status->id,
            'descricao_problema' => 'Teste',
        ]);

        $osServico = OSServico::create(['os_id' => $this->os->id, 'servico_id' => $servico->id]);
        OSServicoInsumo::create([
            'os_servico_id' => $osServico->id,
            'insumo_id' => $this->insumo->id,
            'quantidade' => 3,
        ]);
    }

    private function criarOrcamento(string $status = 'pendente', ?string $token = 'token-valido'): void
    {
        OSOrcamento::create([
            'os_id' => $this->os->id,
            'valor_total' => 200.00,
            'data_orcamento' => now(),
            'status' => $status,
            'approval_token' => $token,
        ]);
    }

    public function test_aprovacao_com_token_valido_reduz_estoque_e_muda_status(): void
    {
        $this->criarOrcamento();

        $resultado = $this->useCase->executar($this->os->id, 'token-valido');

        $this->assertEquals('aprovado', $resultado->getStatus()->value);
        $this->assertNotNull($resultado->getDataAprovacao());
        $this->assertEquals(7, $this->insumo->fresh()->quantidade_estoque);

        $statusEmExecucao = Status::where('nome', 'Em execução')->first();
        $this->assertEquals($statusEmExecucao->id, $this->os->fresh()->status_atual_id);
    }

    public function test_token_invalido_e_rejeitado(): void
    {
        $this->criarOrcamento();

        $this->expectException(TokenAprovacaoInvalido::class);
        $this->useCase->executar($this->os->id, 'token-errado');
    }

    public function test_token_ausente_e_rejeitado(): void
    {
        $this->criarOrcamento();

        $this->expectException(TokenAprovacaoInvalido::class);
        $this->useCase->executar($this->os->id, null);
    }

    public function test_token_invalidado_apos_uso_impede_replay(): void
    {
        $this->criarOrcamento();

        $this->useCase->executar($this->os->id, 'token-valido');

        // Token já consumido — segunda tentativa não encontra a OS pelo token.
        $this->expectException(TokenAprovacaoInvalido::class);
        $this->useCase->executar($this->os->id, 'token-valido');
    }

    public function test_estoque_insuficiente_faz_rollback_completo(): void
    {
        $this->insumo->update(['quantidade_estoque' => 1]);
        $this->criarOrcamento();

        try {
            $this->useCase->executar($this->os->id, 'token-valido');
            $this->fail('Esperava EstoqueInsuficienteException.');
        } catch (EstoqueInsuficienteException $e) {
            // esperado
        }

        $this->assertEquals(1, $this->insumo->fresh()->quantidade_estoque);
        $orcamento = OSOrcamento::where('os_id', $this->os->id)->first();
        $this->assertEquals('pendente', $orcamento->status);
    }

    public function test_aprovar_orcamento_ja_aprovado_lanca_excecao(): void
    {
        $this->criarOrcamento('aprovado');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pendentes');
        $this->useCase->executar($this->os->id, 'token-valido');
    }
}
