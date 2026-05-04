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
use App\Models\User;
use Application\OS\UseCases\AprovarOrcamento;
use Database\Seeders\StatusSeeder;
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
        $this->useCase = new AprovarOrcamento();

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

    public function test_aprovacao_com_sucesso_reduz_estoque_e_muda_status(): void
    {
        OSOrcamento::create([
            'os_id' => $this->os->id,
            'valor_total' => 200.00,
            'data_orcamento' => now(),
            'status' => 'pendente',
        ]);

        $resultado = $this->useCase->executar($this->os->id);

        $this->assertEquals('aprovado', $resultado->status);
        $this->assertNotNull($resultado->data_aprovacao);
        $this->assertEquals(7, $this->insumo->fresh()->quantidade_estoque);
    }

    public function test_estoque_insuficiente_faz_rollback_completo(): void
    {
        $this->insumo->update(['quantidade_estoque' => 1]);
        OSOrcamento::create([
            'os_id' => $this->os->id,
            'valor_total' => 200.00,
            'data_orcamento' => now(),
            'status' => 'pendente',
        ]);

        $this->expectException(EstoqueInsuficienteException::class);
        $this->useCase->executar($this->os->id);

        $this->assertEquals(1, $this->insumo->fresh()->quantidade_estoque);
        $orcamento = OSOrcamento::where('os_id', $this->os->id)->first();
        $this->assertEquals('pendente', $orcamento->status);
    }

    public function test_aprovar_orcamento_ja_aprovado_lanca_excecao(): void
    {
        OSOrcamento::create([
            'os_id' => $this->os->id,
            'valor_total' => 200.00,
            'data_orcamento' => now(),
            'status' => 'aprovado',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pendentes');
        $this->useCase->executar($this->os->id);
    }
}
