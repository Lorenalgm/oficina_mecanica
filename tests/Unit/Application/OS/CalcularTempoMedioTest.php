<?php

namespace Tests\Unit\Application\OS;

use App\Models\Cliente;
use App\Models\OS;
use App\Models\OSStatus;
use App\Models\Status;
use App\Models\Veiculo;
use Application\OS\UseCases\CalcularTempoMedio;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalcularTempoMedioTest extends TestCase
{
    use RefreshDatabase;

    private CalcularTempoMedio $useCase;
    private OS $os;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
        $this->useCase = app(CalcularTempoMedio::class);

        $cliente = Cliente::factory()->create();
        $veiculo = Veiculo::factory()->create(['cliente_id' => $cliente->id]);
        $statusRecebida = Status::where('nome', 'Recebida')->first();

        $this->os = OS::create([
            'cliente_id' => $cliente->id,
            'veiculo_id' => $veiculo->id,
            'status_atual_id' => $statusRecebida->id,
            'descricao_problema' => 'Teste',
        ]);
    }

    public function test_sem_os_finalizadas_retorna_null(): void
    {
        $resultado = $this->useCase->executar();

        $this->assertNull($resultado);
    }

    public function test_com_os_finalizada_retorna_media_em_minutos(): void
    {
        $statusExecucao = Status::where('nome', 'Em execução')->first();
        $statusFinalizada = Status::where('nome', 'Finalizada')->first();

        OSStatus::create([
            'os_id' => $this->os->id,
            'status_id' => $statusExecucao->id,
            'data_status' => now()->subMinutes(120),
        ]);
        OSStatus::create([
            'os_id' => $this->os->id,
            'status_id' => $statusFinalizada->id,
            'data_status' => now(),
        ]);

        $resultado = $this->useCase->executar();

        $this->assertNotNull($resultado);
        $this->assertEqualsWithDelta(120.0, $resultado, 1.0);
    }

    public function test_os_apenas_em_execucao_nao_incluida_na_media(): void
    {
        $statusExecucao = Status::where('nome', 'Em execução')->first();

        OSStatus::create([
            'os_id' => $this->os->id,
            'status_id' => $statusExecucao->id,
            'data_status' => now()->subMinutes(60),
        ]);

        $resultado = $this->useCase->executar();

        $this->assertNull($resultado);
    }
}
