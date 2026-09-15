<?php

namespace Tests\Feature;

use App\Support\CorrelationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\AutenticaComJwt;
use Tests\TestCase;

class ObservabilidadeTest extends TestCase
{
    use RefreshDatabase, AutenticaComJwt;

    protected function tearDown(): void
    {
        CorrelationContext::clear();
        parent::tearDown();
    }

    public function test_resposta_devolve_correlation_id_gerado(): void
    {
        $resposta = $this->getJson('/api/clientes', $this->cabecalhoJwt());

        $correlationId = $resposta->headers->get('X-Request-Id');

        $this->assertNotEmpty($correlationId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $correlationId,
        );
    }

    public function test_correlation_id_recebido_e_propagado(): void
    {
        $this->getJson('/api/clientes', array_merge(
            $this->cabecalhoJwt(),
            ['X-Request-Id' => 'id-vindo-do-gateway'],
        ))->assertHeader('X-Request-Id', 'id-vindo-do-gateway');
    }

    public function test_requisicao_gera_log_estruturado_com_latencia(): void
    {
        Log::spy();

        $this->getJson('/api/clientes', $this->cabecalhoJwt());

        Log::shouldHaveReceived('log')->once()->withArgs(
            function (string $nivel, string $mensagem, array $contexto) {
                return $nivel === 'info'
                    && $mensagem === 'http_request'
                    && $contexto['route'] === 'api/clientes'
                    && $contexto['status'] === 200
                    && is_float($contexto['duration_ms']);
            }
        );
    }

    public function test_erro_do_servidor_e_logado_como_erro(): void
    {
        Log::spy();

        $this->getJson('/api/clientes/999', $this->cabecalhoJwt());

        Log::shouldHaveReceived('log')->once()->withArgs(
            fn (string $nivel, string $mensagem, array $contexto) => $contexto['status'] === 404
        );
    }
}
