<?php

namespace Tests\Feature;

use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicoTest extends TestCase
{
    use RefreshDatabase;

    private function autenticado(): string
    {
        $usuario = User::factory()->create();
        return $usuario->createToken('teste')->plainTextToken;
    }

    public function test_listar_servicos(): void
    {
        $token = $this->autenticado();
        Servico::factory()->count(3)->create();

        $this->getJson('/api/servicos', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_criar_servico(): void
    {
        $token = $this->autenticado();

        $this->postJson('/api/servicos', ['nome' => 'Troca de óleo', 'valor' => 80.00], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(201)
            ->assertJsonPath('data.nome', 'Troca de óleo')
            ->assertJson(['data' => ['valor' => 80]]);
    }

    public function test_criar_servico_sem_nome_retorna_422(): void
    {
        $token = $this->autenticado();

        $this->postJson('/api/servicos', ['valor' => 80.00], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);
    }

    public function test_buscar_servico_existente(): void
    {
        $token = $this->autenticado();
        $model = Servico::factory()->create();

        $this->getJson("/api/servicos/{$model->id}", ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $model->id);
    }

    public function test_buscar_servico_inexistente_retorna_404(): void
    {
        $token = $this->autenticado();

        $this->getJson('/api/servicos/9999', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404);
    }

    public function test_atualizar_servico(): void
    {
        $token = $this->autenticado();
        $model = Servico::factory()->create();

        $this->putJson("/api/servicos/{$model->id}", ['nome' => 'Novo nome', 'valor' => 120.00], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('data.nome', 'Novo nome');
    }

    public function test_remover_servico(): void
    {
        $token = $this->autenticado();
        $model = Servico::factory()->create();

        $this->deleteJson("/api/servicos/{$model->id}", [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(204);

        $this->assertDatabaseMissing('servicos', ['id' => $model->id]);
    }

    public function test_atualizar_servico_inexistente_retorna_404(): void
    {
        $token = $this->autenticado();

        $this->putJson('/api/servicos/9999', ['nome' => 'X', 'valor' => 10.00], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404);
    }

    public function test_acesso_sem_token_retorna_401(): void
    {
        $this->getJson('/api/servicos')->assertStatus(401);
    }
}
