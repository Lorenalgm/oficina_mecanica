<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RotaInternaClienteTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-interno-de-teste';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.interno.token' => self::TOKEN]);
    }

    private function buscar(string $documento, ?string $token = self::TOKEN)
    {
        return $this->getJson(
            "/internal/clientes/por-documento/{$documento}",
            $token === null ? [] : ['X-Internal-Token' => $token],
        );
    }

    public function test_devolve_id_e_nome_do_cliente_pelo_documento(): void
    {
        $cliente = Cliente::factory()->create(['nome' => 'Ana Souza', 'documento' => '52998224725']);

        $this->buscar('529.982.247-25')
            ->assertOk()
            ->assertExactJson(['data' => ['id' => $cliente->id, 'nome' => 'Ana Souza', 'ativo' => true]])
            ->assertHeader('X-Request-Id');
    }

    public function test_informa_cliente_inativo_para_a_lambda_recusar(): void
    {
        Cliente::factory()->create(['documento' => '52998224725', 'ativo' => false]);

        $this->buscar('52998224725')->assertOk()->assertJsonPath('data.ativo', false);
    }

    public function test_cliente_inexistente_retorna_404(): void
    {
        $this->buscar('52998224725')->assertNotFound();
    }

    public function test_documento_invalido_retorna_422(): void
    {
        $this->buscar('12345678900')->assertStatus(422);
    }

    public function test_sem_token_retorna_401_e_registra_a_tentativa(): void
    {
        Log::spy();

        $this->buscar('52998224725', null)->assertUnauthorized();

        Log::shouldHaveReceived('warning')->withArgs(
            fn ($mensagem, $contexto) => $contexto['event_type'] === 'interno.token_invalido' && $contexto['configurado'] === true
        )->once();
    }

    public function test_token_errado_retorna_401(): void
    {
        $this->buscar('52998224725', 'outro-token')->assertUnauthorized();
    }

    public function test_sem_token_configurado_a_rota_fica_fechada(): void
    {
        config(['services.interno.token' => null]);

        $this->buscar('52998224725', '')->assertUnauthorized();
    }

    public function test_jwt_nao_substitui_o_token_interno(): void
    {
        $this->getJson('/internal/clientes/por-documento/52998224725', ['Authorization' => 'Bearer qualquer'])
            ->assertUnauthorized();
    }
}
