<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\AutenticaComJwt;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, AutenticaComJwt;

    private function criarAdmin(): User
    {
        return User::factory()->create([
            'email' => 'admin@oficina.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_login_com_credenciais_validas_retorna_token(): void
    {
        $this->criarAdmin();

        $this->postJson('/api/login', ['email' => 'admin@oficina.com', 'password' => 'password'])
            ->assertStatus(200)
            ->assertJsonStructure(['token']);
    }

    public function test_login_com_senha_errada_retorna_401(): void
    {
        $this->criarAdmin();

        $this->postJson('/api/login', ['email' => 'admin@oficina.com', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    public function test_login_com_email_inexistente_retorna_401(): void
    {
        $this->postJson('/api/login', ['email' => 'nao@existe.com', 'password' => 'password'])
            ->assertStatus(401);
    }

    public function test_login_sem_campos_retorna_422(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422);
    }

    public function test_logout_com_token_valido(): void
    {
        $usuario = $this->criarAdmin();
        $token = $usuario->createToken('teste')->plainTextToken;

        $this->postJson('/api/logout', [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);
    }

    public function test_logout_sem_token_retorna_401(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }

    public function test_acesso_a_rota_protegida_com_jwt_valido(): void
    {
        $this->getJson('/api/clientes', $this->cabecalhoJwt())
            ->assertStatus(200);
    }

    public function test_token_do_sanctum_nao_abre_rota_protegida_por_jwt(): void
    {
        // As rotas de negócio passaram a exigir o JWT emitido pela Lambda a
        // partir do CPF; o token do Sanctum serve apenas ao painel interno.
        $token = $this->criarAdmin()->createToken('teste')->plainTextToken;

        $this->getJson('/api/clientes', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(401);
    }

    public function test_acesso_a_rota_protegida_sem_token_retorna_401(): void
    {
        $this->getJson('/api/clientes')->assertStatus(401);
    }

    public function test_acesso_a_rota_protegida_com_token_invalido_retorna_401(): void
    {
        $this->getJson('/api/clientes', ['Authorization' => 'Bearer token-invalido'])
            ->assertStatus(401);
    }

}
