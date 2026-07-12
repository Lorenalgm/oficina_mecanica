<?php

namespace Tests\Feature;

use App\Mail\StatusOSAtualizado;
use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\OS;
use App\Models\OSOrcamento;
use App\Models\OSServico;
use App\Models\OSServicoInsumo;
use App\Models\Servico;
use App\Models\Status;
use App\Models\User;
use App\Models\Veiculo;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OSFase2Test extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Cliente $cliente;
    private Veiculo $veiculo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
        $this->token = User::factory()->create()->createToken('teste')->plainTextToken;
        $this->cliente = Cliente::factory()->create();
        $this->veiculo = Veiculo::factory()->create(['cliente_id' => $this->cliente->id]);
    }

    private function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function criarOSComStatus(string $statusNome, ?Carbon $createdAt = null): OS
    {
        $status = Status::where('nome', $statusNome)->firstOrFail();
        $os = OS::create([
            'cliente_id' => $this->cliente->id,
            'veiculo_id' => $this->veiculo->id,
            'status_atual_id' => $status->id,
            'descricao_problema' => "OS {$statusNome}",
        ]);

        if ($createdAt !== null) {
            $os->created_at = $createdAt;
            $os->save();
        }

        return $os;
    }

    // ---- 7.4 Listagem ordenada ----

    public function test_listagem_exclui_finalizada_e_entregue(): void
    {
        $this->criarOSComStatus('Recebida');
        $this->criarOSComStatus('Finalizada');
        $this->criarOSComStatus('Entregue');

        $resposta = $this->getJson('/api/os', $this->headers())
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame('Recebida', $resposta->json('data.0.status_atual.nome'));
    }

    public function test_listagem_ordena_por_prioridade_de_status(): void
    {
        $this->criarOSComStatus('Recebida');
        $this->criarOSComStatus('Em diagnóstico');
        $this->criarOSComStatus('Aguardando aprovação');
        $this->criarOSComStatus('Em execução');

        $resposta = $this->getJson('/api/os', $this->headers())->assertStatus(200);

        $nomes = array_map(fn ($os) => $os['status_atual']['nome'], $resposta->json('data'));

        $this->assertSame(
            ['Em execução', 'Aguardando aprovação', 'Em diagnóstico', 'Recebida'],
            $nomes,
        );
    }

    public function test_listagem_mesmo_status_mais_antigas_primeiro(): void
    {
        $antiga = $this->criarOSComStatus('Em execução', now()->subDays(3));
        $recente = $this->criarOSComStatus('Em execução', now());

        $resposta = $this->getJson('/api/os', $this->headers())->assertStatus(200);

        $ids = array_map(fn ($os) => $os['id'], $resposta->json('data'));

        $this->assertSame([$antiga->id, $recente->id], $ids);
    }

    // ---- 7.5 Webhook público de aprovação/recusa ----

    private function criarOSComOrcamentoPendente(string $token): OS
    {
        $servico = Servico::factory()->create(['valor' => 100.00]);
        $insumo = Insumo::factory()->create(['valor' => 50.00, 'quantidade_estoque' => 10]);
        $os = $this->criarOSComStatus('Aguardando aprovação');
        $osServico = OSServico::create(['os_id' => $os->id, 'servico_id' => $servico->id]);
        OSServicoInsumo::create([
            'os_servico_id' => $osServico->id,
            'insumo_id' => $insumo->id,
            'quantidade' => 2,
        ]);
        OSOrcamento::create([
            'os_id' => $os->id,
            'valor_total' => 200.00,
            'data_orcamento' => now(),
            'status' => 'pendente',
            'approval_token' => $token,
        ]);

        return $os;
    }

    public function test_aprovar_sem_token_retorna_401(): void
    {
        $os = $this->criarOSComOrcamentoPendente('token-webhook');

        $this->postJson("/api/os/{$os->id}/orcamento/aprovar", [])
            ->assertStatus(401);

        $this->assertDatabaseHas('os_orcamentos', ['os_id' => $os->id, 'status' => 'pendente']);
    }

    public function test_recusar_sem_token_retorna_401(): void
    {
        $os = $this->criarOSComOrcamentoPendente('token-webhook');

        $this->postJson("/api/os/{$os->id}/orcamento/recusar", [])
            ->assertStatus(401);

        $this->assertDatabaseHas('os_orcamentos', ['os_id' => $os->id, 'status' => 'pendente']);
    }

    public function test_aprovar_com_token_valido_e_publico(): void
    {
        $os = $this->criarOSComOrcamentoPendente('token-webhook');

        // Sem cabeçalho de autenticação — rota pública.
        $this->postJson("/api/os/{$os->id}/orcamento/aprovar", ['token' => 'token-webhook'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'aprovado');
    }

    public function test_token_reusado_retorna_401(): void
    {
        $os = $this->criarOSComOrcamentoPendente('token-webhook');

        $this->postJson("/api/os/{$os->id}/orcamento/aprovar", ['token' => 'token-webhook'])
            ->assertStatus(200);

        // Token já consumido (uso único) — replay rejeitado.
        $this->postJson("/api/os/{$os->id}/orcamento/aprovar", ['token' => 'token-webhook'])
            ->assertStatus(401);
    }

    // ---- 7.6 E-mail na alteração de status ----

    public function test_alteracao_de_status_envia_email_ao_cliente(): void
    {
        Mail::fake();
        $os = $this->criarOSComStatus('Recebida');
        $novoStatus = Status::where('nome', 'Em diagnóstico')->first();

        $this->patchJson("/api/os/{$os->id}/status", ['status_id' => $novoStatus->id], $this->headers())
            ->assertStatus(200);

        $this->assertDatabaseHas('os_status', [
            'os_id' => $os->id,
            'status_id' => $novoStatus->id,
        ]);

        Mail::assertSent(
            StatusOSAtualizado::class,
            fn (StatusOSAtualizado $mail) => $mail->hasTo($this->cliente->email),
        );
    }

    public function test_cliente_sem_email_nao_impede_alteracao_de_status(): void
    {
        Mail::fake();
        $clienteSemEmail = Cliente::factory()->create(['email' => null]);
        $veiculo = Veiculo::factory()->create(['cliente_id' => $clienteSemEmail->id]);
        $statusRecebida = Status::where('nome', 'Recebida')->first();
        $os = OS::create([
            'cliente_id' => $clienteSemEmail->id,
            'veiculo_id' => $veiculo->id,
            'status_atual_id' => $statusRecebida->id,
            'descricao_problema' => 'Sem e-mail',
        ]);
        $novoStatus = Status::where('nome', 'Em diagnóstico')->first();

        $this->patchJson("/api/os/{$os->id}/status", ['status_id' => $novoStatus->id], $this->headers())
            ->assertStatus(200);

        $this->assertDatabaseHas('os_status', [
            'os_id' => $os->id,
            'status_id' => $novoStatus->id,
        ]);

        Mail::assertNothingSent();
    }
}
