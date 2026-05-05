<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VeiculoTest extends TestCase
{
    use RefreshDatabase;

    private function autenticado(): string
    {
        return User::factory()->create()->createToken('teste')->plainTextToken;
    }

    public function test_criar_veiculo_placa_valida(): void
    {
        $token = $this->autenticado();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/veiculos', [
            'placa' => 'ABC-1234',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'ano' => 2020,
            'cliente_id' => $cliente->id,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(201)
            ->assertJsonPath('data.placa', 'ABC-1234');
    }

    public function test_criar_veiculo_placa_mercosul(): void
    {
        $token = $this->autenticado();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/veiculos', [
            'placa' => 'ABC1D23',
            'marca' => 'Honda',
            'modelo' => 'Civic',
            'ano' => 2022,
            'cliente_id' => $cliente->id,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(201);
    }

    public function test_criar_veiculo_placa_invalida_retorna_422(): void
    {
        $token = $this->autenticado();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/veiculos', [
            'placa' => '1234XYZ',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'ano' => 2020,
            'cliente_id' => $cliente->id,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);
    }

    public function test_criar_veiculo_placa_duplicada_retorna_422(): void
    {
        $token = $this->autenticado();
        $cliente = Cliente::factory()->create();
        Veiculo::factory()->create(['placa' => 'ABC-1234', 'cliente_id' => $cliente->id]);

        $this->postJson('/api/veiculos', [
            'placa' => 'ABC-1234',
            'marca' => 'Ford',
            'modelo' => 'Fiesta',
            'ano' => 2021,
            'cliente_id' => $cliente->id,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);
    }

    public function test_criar_veiculo_cliente_inexistente_retorna_422(): void
    {
        $token = $this->autenticado();

        $this->postJson('/api/veiculos', [
            'placa' => 'XYZ-9999',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'ano' => 2020,
            'cliente_id' => 9999,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);
    }

    public function test_listar_veiculos(): void
    {
        $token = $this->autenticado();
        Veiculo::factory()->count(2)->create();

        $this->getJson('/api/veiculos', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_buscar_veiculo_existente(): void
    {
        $token = $this->autenticado();
        $model = Veiculo::factory()->create();

        $this->getJson("/api/veiculos/{$model->id}", ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $model->id);
    }

    public function test_buscar_veiculo_inexistente_retorna_404(): void
    {
        $token = $this->autenticado();

        $this->getJson('/api/veiculos/9999', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404);
    }

    public function test_atualizar_veiculo(): void
    {
        $token = $this->autenticado();
        $model = Veiculo::factory()->create();

        $this->putJson("/api/veiculos/{$model->id}", [
            'marca' => 'Ford',
            'modelo' => 'Fiesta',
            'ano' => 2021,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('data.marca', 'Ford');
    }

    public function test_remover_veiculo(): void
    {
        $token = $this->autenticado();
        $model = Veiculo::factory()->create();

        $this->deleteJson("/api/veiculos/{$model->id}", [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(204);
    }

    public function test_atualizar_veiculo_inexistente_retorna_404(): void
    {
        $token = $this->autenticado();

        $this->putJson('/api/veiculos/9999', ['marca' => 'X', 'modelo' => 'Y', 'ano' => 2020], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404);
    }

    public function test_acesso_sem_token_retorna_401(): void
    {
        $this->getJson('/api/veiculos')->assertStatus(401);
    }
}
