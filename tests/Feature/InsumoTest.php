<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsumoTest extends TestCase
{
    use RefreshDatabase;

    private function autenticado(): string
    {
        $usuario = User::factory()->create();
        return $usuario->createToken('teste')->plainTextToken;
    }

    public function test_listar_insumos(): void
    {
        $token = $this->autenticado();
        Insumo::factory()->count(2)->create();

        $this->getJson('/api/insumos', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_criar_insumo(): void
    {
        $token = $this->autenticado();

        $this->postJson('/api/insumos', ['nome' => 'Filtro', 'valor' => 25.00, 'quantidade_estoque' => 50], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(201)
            ->assertJsonPath('data.quantidade_estoque', 50);
    }

    public function test_criar_insumo_estoque_negativo_retorna_422(): void
    {
        $token = $this->autenticado();

        $this->postJson('/api/insumos', ['nome' => 'Filtro', 'valor' => 25.00, 'quantidade_estoque' => -1], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);
    }

    public function test_buscar_insumo_existente(): void
    {
        $token = $this->autenticado();
        $model = Insumo::factory()->create();

        $this->getJson("/api/insumos/{$model->id}", ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $model->id);
    }

    public function test_buscar_insumo_inexistente_retorna_404(): void
    {
        $token = $this->autenticado();

        $this->getJson('/api/insumos/9999', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404);
    }

    public function test_atualizar_insumo(): void
    {
        $token = $this->autenticado();
        $model = Insumo::factory()->create();

        $this->putJson("/api/insumos/{$model->id}", ['nome' => 'Novo filtro', 'valor' => 30.00, 'quantidade_estoque' => 100], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('data.quantidade_estoque', 100);
    }

    public function test_remover_insumo(): void
    {
        $token = $this->autenticado();
        $model = Insumo::factory()->create();

        $this->deleteJson("/api/insumos/{$model->id}", [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(204);

        $this->assertDatabaseMissing('insumos', ['id' => $model->id]);
    }

    public function test_atualizar_insumo_inexistente_retorna_404(): void
    {
        $token = $this->autenticado();

        $this->putJson('/api/insumos/9999', ['nome' => 'X', 'valor' => 10.00, 'quantidade_estoque' => 1], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404);
    }

    public function test_acesso_sem_token_retorna_401(): void
    {
        $this->getJson('/api/insumos')->assertStatus(401);
    }
}
