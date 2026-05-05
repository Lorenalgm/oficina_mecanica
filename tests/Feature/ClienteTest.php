<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;

    private function autenticado(): string
    {
        return User::factory()->create()->createToken('teste')->plainTextToken;
    }

    public function test_criar_cliente_com_cpf_valido(): void
    {
        $token = $this->autenticado();

        $this->postJson('/api/clientes', [
            'nome' => 'João Silva',
            'documento' => '529.982.247-25',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(201)
            ->assertJsonPath('data.nome', 'João Silva');
    }

    public function test_criar_cliente_com_cnpj_valido(): void
    {
        $token = $this->autenticado();

        $this->postJson('/api/clientes', [
            'nome' => 'Empresa Ltda',
            'documento' => '11.222.333/0001-81',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(201);
    }

    public function test_criar_cliente_com_cpf_invalido_retorna_422(): void
    {
        $token = $this->autenticado();

        $this->postJson('/api/clientes', [
            'nome' => 'João',
            'documento' => '000.000.000-00',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);
    }

    public function test_criar_cliente_com_documento_duplicado_retorna_422(): void
    {
        $token = $this->autenticado();
        Cliente::factory()->create(['documento' => '52998224725']);

        $this->postJson('/api/clientes', [
            'nome' => 'Outro',
            'documento' => '529.982.247-25',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422);
    }

    public function test_listar_clientes(): void
    {
        $token = $this->autenticado();
        Cliente::factory()->count(2)->create();

        $this->getJson('/api/clientes', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_buscar_cliente_existente(): void
    {
        $token = $this->autenticado();
        $model = Cliente::factory()->create();

        $this->getJson("/api/clientes/{$model->id}", ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $model->id);
    }

    public function test_buscar_cliente_inexistente_retorna_404(): void
    {
        $token = $this->autenticado();

        $this->getJson('/api/clientes/9999', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404);
    }

    public function test_atualizar_cliente(): void
    {
        $token = $this->autenticado();
        $model = Cliente::factory()->create();

        $this->putJson("/api/clientes/{$model->id}", [
            'nome' => 'Novo Nome',
            'celular' => '(11) 99999-9999',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJsonPath('data.nome', 'Novo Nome');
    }

    public function test_remover_cliente(): void
    {
        $token = $this->autenticado();
        $model = Cliente::factory()->create();

        $this->deleteJson("/api/clientes/{$model->id}", [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(204);
    }

    public function test_atualizar_cliente_inexistente_retorna_404(): void
    {
        $token = $this->autenticado();

        $this->putJson('/api/clientes/9999', ['nome' => 'Foo'], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404);
    }

    public function test_acesso_sem_token_retorna_401(): void
    {
        $this->getJson('/api/clientes')->assertStatus(401);
    }
}
