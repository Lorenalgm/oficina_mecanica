<?php

namespace Tests\Feature\Saga;

use App\Models\OS;
use App\Models\Status;
use Domain\Shared\Messaging\Envelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AutenticaComJwt;
use Tests\Concerns\CriaOSParaSaga;
use Tests\TestCase;

class SolicitarOrcamentoTest extends TestCase
{
    use RefreshDatabase, AutenticaComJwt, CriaOSParaSaga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogo();
    }

    private function solicitar(int $osId, array $cabecalhos = [])
    {
        return $this->postJson("/api/os/{$osId}/orcamento", [], $this->cabecalhoJwt() + $cabecalhos);
    }

    private function comandos(): array
    {
        return DB::table('outbox')->orderBy('criado_em')->pluck('corpo')
            ->map(fn (string $c) => Envelope::deJson($c))->all();
    }

    public function test_inicia_a_saga_e_pede_o_orcamento_ao_billing(): void
    {
        $os = $this->criarOSComServico(quantidadeInsumo: 2);

        $resposta = $this->solicitar($os->id, ['X-Request-Id' => 'corr-da-requisicao'])
            ->assertStatus(202)
            ->assertJsonPath('data.os_id', $os->id)
            ->assertJsonPath('data.estado', 'aguardando_orcamento')
            ->assertJsonPath('data.acompanhar', "/api/os/{$os->id}/saga");

        $sagaId = $resposta->json('data.saga_id');
        $this->assertDatabaseHas('sagas', ['id' => $sagaId, 'os_id' => $os->id, 'estado' => 'aguardando_orcamento']);
        $this->assertSame('Em diagnóstico', $this->statusDaOS($os->id));

        [$comando] = $this->comandos();
        $this->assertSame('billing.cmd.gerar-orcamento', $comando->type);
        $this->assertSame($sagaId, $comando->sagaId);
        $this->assertSame('corr-da-requisicao', $comando->correlationId);
        $this->assertSame($os->id, $comando->osId);
        $this->assertSame([
            'cliente' => [
                'id' => $this->cliente->id,
                'nome' => 'Ana Souza',
                'email' => 'ana@exemplo.com',
                'documento' => $this->cliente->documento,
            ],
            'veiculo' => ['placa' => 'ABC1D23', 'modelo' => 'Fiat Uno'],
            'itens' => [
                ['tipo' => 'servico', 'referencia_id' => $this->servico->id, 'descricao' => 'Troca de óleo', 'quantidade' => 1, 'valor_unitario' => 10000],
                ['tipo' => 'insumo', 'referencia_id' => $this->insumo->id, 'descricao' => 'Óleo 5W30', 'quantidade' => 2, 'valor_unitario' => 4990],
            ],
            'valor_total' => 19980,
        ], $comando->payload);

        $this->assertDatabaseHas('sagas', ['id' => $sagaId, 'ultimo_comando_id' => $comando->messageId]);
        $this->assertSame(['billing.cmd.gerar-orcamento'], DB::table('saga_passos')->where('saga_id', $sagaId)->pluck('passo')->all());
    }

    public function test_nao_inicia_segunda_saga_enquanto_a_primeira_esta_ativa(): void
    {
        $os = $this->criarOSComServico();
        $primeira = $this->solicitar($os->id)->json('data.saga_id');

        $this->solicitar($os->id)
            ->assertStatus(409)
            ->assertJsonPath('saga_id', $primeira);

        $this->assertDatabaseCount('sagas', 1);
        $this->assertDatabaseCount('outbox', 1);
    }

    public function test_permite_nova_saga_depois_que_a_anterior_terminou(): void
    {
        $os = $this->criarOSComServico();
        $primeira = $this->solicitar($os->id)->json('data.saga_id');
        DB::table('sagas')->where('id', $primeira)->update(['estado' => 'compensada']);

        $this->solicitar($os->id)->assertStatus(202);

        $this->assertDatabaseCount('sagas', 2);
    }

    public function test_os_sem_servicos_nao_inicia_saga(): void
    {
        $os = OS::create([
            'cliente_id' => $this->cliente->id,
            'veiculo_id' => $this->veiculo->id,
            'status_atual_id' => Status::where('nome', 'Recebida')->value('id'),
            'descricao_problema' => 'Sem serviço',
        ]);

        $this->solicitar($os->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'A OS não possui serviços para gerar orçamento.');

        $this->assertDatabaseCount('sagas', 0);
        $this->assertDatabaseCount('outbox', 0);
        $this->assertSame('Recebida', $this->statusDaOS($os->id));
    }

    public function test_os_inexistente_retorna_422(): void
    {
        $this->solicitar(999999)->assertStatus(422);

        $this->assertDatabaseCount('sagas', 0);
    }
}
