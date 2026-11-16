<?php

namespace Tests\Feature\Saga;

use App\Models\Cliente;
use App\Models\OS;
use App\Models\Status;
use App\Models\Veiculo;
use Database\Seeders\StatusSeeder;
use Domain\Atendimento\Saga\Compensacao;
use Domain\Atendimento\Saga\EstadoSaga;
use Domain\Atendimento\Saga\SagaOS;
use Domain\Atendimento\Saga\SagaRepository;
use Domain\Shared\Messaging\Envelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentSagaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SagaRepository $repositorio;
    private int $osId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
        $this->repositorio = app(SagaRepository::class);

        $cliente = Cliente::factory()->create();
        $this->osId = OS::create([
            'cliente_id' => $cliente->id,
            'veiculo_id' => Veiculo::factory()->create(['cliente_id' => $cliente->id])->id,
            'status_atual_id' => Status::where('nome', 'Recebida')->value('id'),
            'descricao_problema' => 'Teste',
        ])->id;
    }

    private function nova(): SagaOS
    {
        return SagaOS::iniciar(Envelope::novoUuid(), $this->osId, 'corr-1');
    }

    public function test_salva_e_recupera_todos_os_campos(): void
    {
        $saga = $this->nova();
        $saga->orcamentoGerado(7, 15000, 'https://billing/aprovar/7');
        $saga->orcamentoAprovado();
        $saga->estoqueBaixadoComSucesso();
        $saga->cobrancaCriada(9, 'https://mp/pagar/9');
        $saga->pagamentoConfirmado(9);
        $saga->falhar('fila cheia');
        $saga->compensacaoFalhou(Compensacao::EstornarPagamento, 3);
        $saga->registrarComando('0b9f6c1e-2a3b-4c5d-8e9f-0a1b2c3d4e5f');

        $this->repositorio->salvar($saga);

        $this->assertEquals($saga, $this->repositorio->buscar($saga->id));
    }

    public function test_salvar_de_novo_atualiza_a_mesma_saga(): void
    {
        $saga = $this->nova();
        $this->repositorio->salvar($saga);
        $saga->orcamentoGerado(7, 15000, 'link');

        $this->repositorio->salvar($saga);

        $this->assertSame(EstadoSaga::AguardandoAprovacao, $this->repositorio->buscar($saga->id)->estado());
        $this->assertDatabaseCount('sagas', 1);
    }

    public function test_busca_inexistente_retorna_null(): void
    {
        $this->assertNull($this->repositorio->buscar(Envelope::novoUuid()));
        $this->assertNull($this->repositorio->buscarAtivaDaOS($this->osId));
        $this->assertNull($this->repositorio->buscarUltimaDaOS($this->osId));
    }

    public function test_saga_ativa_ignora_as_encerradas(): void
    {
        $encerrada = $this->nova();
        $encerrada->falhar('orçamento falhou');
        $encerrada->compensacaoConcluida(Compensacao::CancelarOS);
        $this->repositorio->salvar($encerrada);

        $this->assertNull($this->repositorio->buscarAtivaDaOS($this->osId));

        $ativa = $this->nova();
        $this->repositorio->salvar($ativa);

        $this->assertSame($ativa->id, $this->repositorio->buscarAtivaDaOS($this->osId)?->id);
    }

    public function test_ultima_saga_da_os_e_a_mais_recente(): void
    {
        $primeira = $this->nova();
        $primeira->falhar('orçamento falhou');
        $this->repositorio->salvar($primeira);
        $this->travel(1)->minutes();
        $segunda = $this->nova();
        $this->repositorio->salvar($segunda);

        $this->assertSame($segunda->id, $this->repositorio->buscarUltimaDaOS($this->osId)->id);
    }

    public function test_registra_passos_em_ordem(): void
    {
        $saga = $this->nova();
        $this->repositorio->salvar($saga);

        $this->repositorio->registrarPasso($saga->id, 'billing.cmd.gerar-orcamento', ['valor_total' => 15000]);
        $this->repositorio->registrarPasso($saga->id, 'billing.evt.orcamento-gerado');

        $passos = $this->repositorio->passos($saga->id);
        $this->assertSame(['billing.cmd.gerar-orcamento', 'billing.evt.orcamento-gerado'], array_column($passos, 'passo'));
        $this->assertSame(['valor_total' => 15000], $passos[0]['detalhes']);
        $this->assertSame([], $passos[1]['detalhes']);
        $this->assertNotNull($passos[0]['registrado_em']);
    }
}
