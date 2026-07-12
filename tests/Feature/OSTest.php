<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\OS;
use App\Models\OSServico;
use App\Models\OSOrcamento;
use App\Models\Servico;
use App\Models\Status;
use App\Models\User;
use App\Models\Veiculo;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OSTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Cliente $cliente;
    private Veiculo $veiculo;
    private Servico $servico;
    private Insumo $insumo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
        $this->token = User::factory()->create()->createToken('teste')->plainTextToken;
        $this->cliente = Cliente::factory()->create();
        $this->veiculo = Veiculo::factory()->create(['cliente_id' => $this->cliente->id]);
        $this->servico = Servico::factory()->create(['valor' => 100.00]);
        $this->insumo = Insumo::factory()->create(['valor' => 50.00, 'quantidade_estoque' => 10]);
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function criarOS(): OS
    {
        $statusRecebida = Status::where('nome', 'Recebida')->first();
        $os = OS::create([
            'cliente_id' => $this->cliente->id,
            'veiculo_id' => $this->veiculo->id,
            'status_atual_id' => $statusRecebida->id,
            'descricao_problema' => 'Barulho no motor',
        ]);
        \App\Models\OSStatus::create([
            'os_id' => $os->id,
            'status_id' => $statusRecebida->id,
            'data_status' => now(),
        ]);
        return $os;
    }

    public function test_criar_os(): void
    {
        $this->postJson('/api/os', [
            'veiculo_id' => $this->veiculo->id,
            'cliente_id' => $this->cliente->id,
            'descricao_problema' => 'Troca de óleo',
        ], $this->headers())
            ->assertStatus(201)
            ->assertJsonPath('data.descricao_problema', 'Troca de óleo')
            ->assertJsonPath('data.status_atual.nome', 'Recebida');
    }

    public function test_criar_os_sem_campos_obrigatorios_retorna_422(): void
    {
        $this->postJson('/api/os', [], $this->headers())
            ->assertStatus(422);
    }

    public function test_listar_os(): void
    {
        $this->criarOS();

        $this->getJson('/api/os', $this->headers())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_detalhar_os(): void
    {
        $os = $this->criarOS();

        $this->getJson("/api/os/{$os->id}", $this->headers())
            ->assertStatus(200)
            ->assertJsonPath('data.id', $os->id);
    }

    public function test_detalhar_os_inexistente_retorna_404(): void
    {
        $this->getJson('/api/os/999', $this->headers())
            ->assertStatus(404);
    }

    public function test_adicionar_servico_na_os(): void
    {
        $os = $this->criarOS();

        $this->postJson("/api/os/{$os->id}/servicos", [
            'servico_id' => $this->servico->id,
        ], $this->headers())
            ->assertStatus(201)
            ->assertJsonPath('data.servico_id', $this->servico->id);
    }

    public function test_adicionar_insumo_ao_servico(): void
    {
        $os = $this->criarOS();
        $osServico = OSServico::create(['os_id' => $os->id, 'servico_id' => $this->servico->id]);

        $this->postJson("/api/os/{$os->id}/servicos/{$osServico->id}/insumos", [
            'insumo_id' => $this->insumo->id,
            'quantidade' => 2,
        ], $this->headers())
            ->assertStatus(201)
            ->assertJsonPath('data.quantidade', 2);
    }

    public function test_gerar_orcamento(): void
    {
        $os = $this->criarOS();
        $osServico = OSServico::create(['os_id' => $os->id, 'servico_id' => $this->servico->id]);
        \App\Models\OSServicoInsumo::create([
            'os_servico_id' => $osServico->id,
            'insumo_id' => $this->insumo->id,
            'quantidade' => 2,
        ]);

        $this->postJson("/api/os/{$os->id}/orcamento", [], $this->headers())
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pendente')
            ->assertJson(['data' => ['valor_total' => 200]]);
    }

    public function test_aprovar_orcamento_reduz_estoque(): void
    {
        $os = $this->criarOS();
        $osServico = OSServico::create(['os_id' => $os->id, 'servico_id' => $this->servico->id]);
        \App\Models\OSServicoInsumo::create([
            'os_servico_id' => $osServico->id,
            'insumo_id' => $this->insumo->id,
            'quantidade' => 3,
        ]);
        OSOrcamento::create([
            'os_id' => $os->id,
            'valor_total' => 150.00,
            'data_orcamento' => now(),
            'status' => 'pendente',
            'approval_token' => 'token-aprovar-123',
        ]);

        $this->postJson("/api/os/{$os->id}/orcamento/aprovar", ['token' => 'token-aprovar-123'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'aprovado');

        $this->assertEquals(7, $this->insumo->fresh()->quantidade_estoque);
    }

    public function test_aprovar_orcamento_com_estoque_insuficiente_retorna_422(): void
    {
        $this->insumo->update(['quantidade_estoque' => 1]);
        $os = $this->criarOS();
        $osServico = OSServico::create(['os_id' => $os->id, 'servico_id' => $this->servico->id]);
        \App\Models\OSServicoInsumo::create([
            'os_servico_id' => $osServico->id,
            'insumo_id' => $this->insumo->id,
            'quantidade' => 5,
        ]);
        OSOrcamento::create([
            'os_id' => $os->id,
            'valor_total' => 250.00,
            'data_orcamento' => now(),
            'status' => 'pendente',
            'approval_token' => 'token-estoque-insuf',
        ]);

        $this->postJson("/api/os/{$os->id}/orcamento/aprovar", ['token' => 'token-estoque-insuf'])
            ->assertStatus(422);

        $this->assertEquals(1, $this->insumo->fresh()->quantidade_estoque);
    }

    public function test_recusar_orcamento(): void
    {
        $os = $this->criarOS();
        OSOrcamento::create([
            'os_id' => $os->id,
            'valor_total' => 100.00,
            'data_orcamento' => now(),
            'status' => 'pendente',
            'approval_token' => 'token-recusar-123',
        ]);

        $this->postJson("/api/os/{$os->id}/orcamento/recusar", ['token' => 'token-recusar-123'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'recusado');
    }

    public function test_alterar_status_os(): void
    {
        $os = $this->criarOS();
        $statusEmDiagnostico = Status::where('nome', 'Em diagnóstico')->first();

        $this->patchJson("/api/os/{$os->id}/status", [
            'status_id' => $statusEmDiagnostico->id,
        ], $this->headers())
            ->assertStatus(200)
            ->assertJsonPath('data.status_atual.nome', 'Em diagnóstico');
    }

    public function test_tempo_medio_retorna_null_sem_os_finalizadas(): void
    {
        $this->getJson('/api/os/tempo-medio', $this->headers())
            ->assertStatus(200)
            ->assertJson(['tempo_medio_minutos' => null]);
    }

    public function test_consulta_publica_retorna_status_da_os(): void
    {
        $os = $this->criarOS();

        $this->getJson('/api/consulta-publica?' . http_build_query([
            'documento' => $this->cliente->documento,
            'placa' => $this->veiculo->placa,
        ]))
            ->assertStatus(200)
            ->assertJsonPath('data.status_atual', 'Recebida')
            ->assertJsonPath('data.descricao_problema', 'Barulho no motor');
    }

    public function test_consulta_publica_sem_parametros_retorna_422(): void
    {
        $this->getJson('/api/consulta-publica')
            ->assertStatus(422);
    }

    public function test_consulta_publica_sem_os_retorna_404(): void
    {
        $this->getJson('/api/consulta-publica?' . http_build_query([
            'documento' => $this->cliente->documento,
            'placa' => 'ZZZ9Z99',
        ]))
            ->assertStatus(404);
    }

    public function test_listar_os_com_filtro_de_cliente(): void
    {
        $this->criarOS();

        $this->getJson('/api/os?cliente_id=' . $this->cliente->id, $this->headers())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_listar_os_com_filtro_de_status(): void
    {
        $this->criarOS();
        $status = Status::where('nome', 'Recebida')->first();

        $this->getJson('/api/os?status_id=' . $status->id, $this->headers())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_listar_os_com_filtro_de_veiculo(): void
    {
        $this->criarOS();

        $this->getJson('/api/os?veiculo_id=' . $this->veiculo->id, $this->headers())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_adicionar_servico_em_os_inexistente_retorna_422(): void
    {
        $this->postJson('/api/os/9999/servicos', [
            'servico_id' => $this->servico->id,
        ], $this->headers())
            ->assertStatus(422);
    }

    public function test_adicionar_insumo_em_servico_inexistente_retorna_422(): void
    {
        $os = $this->criarOS();

        $this->postJson("/api/os/{$os->id}/servicos/9999/insumos", [
            'insumo_id' => $this->insumo->id,
            'quantidade' => 1,
        ], $this->headers())
            ->assertStatus(422);
    }

    public function test_gerar_orcamento_sem_servicos_retorna_422(): void
    {
        $os = $this->criarOS();

        $this->postJson("/api/os/{$os->id}/orcamento", [], $this->headers())
            ->assertStatus(422);
    }

    public function test_recusar_orcamento_ja_recusado_retorna_422(): void
    {
        $os = $this->criarOS();
        \App\Models\OSOrcamento::create([
            'os_id' => $os->id,
            'valor_total' => 100.00,
            'data_orcamento' => now(),
            'status' => 'recusado',
            'approval_token' => 'token-ja-recusado',
        ]);

        $this->postJson("/api/os/{$os->id}/orcamento/recusar", ['token' => 'token-ja-recusado'])
            ->assertStatus(422);
    }

    public function test_alterar_status_invalido_retorna_422(): void
    {
        $os = $this->criarOS();

        $this->patchJson("/api/os/{$os->id}/status", [
            'status_id' => 9999,
        ], $this->headers())
            ->assertStatus(422);
    }
}
