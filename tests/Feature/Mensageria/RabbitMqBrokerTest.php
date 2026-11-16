<?php

namespace Tests\Feature\Mensageria;

use Infrastructure\Messaging\Decisao;
use Infrastructure\Messaging\FalhaAoPublicar;
use Infrastructure\Messaging\RabbitMq\RabbitMqBroker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integracao')]
class RabbitMqBrokerTest extends TestCase
{
    private AMQPStreamConnection $admin;
    private AMQPChannel $canal;
    private string $sufixo;
    private string $exchange;
    private string $exchangeRetry;
    private string $fila;
    private RabbitMqBroker $broker;

    protected function setUp(): void
    {
        $host = getenv('RABBITMQ_TEST_HOST');
        if (! $host) {
            $this->markTestSkipped('Defina RABBITMQ_TEST_HOST para rodar contra um RabbitMQ real.');
        }

        $porta = (int) (getenv('RABBITMQ_TEST_PORT') ?: 5672);
        $usuario = getenv('RABBITMQ_TEST_USER') ?: 'guest';
        $senha = getenv('RABBITMQ_TEST_PASSWORD') ?: 'guest';

        $this->sufixo = bin2hex(random_bytes(4));
        $this->exchange = "teste.{$this->sufixo}";
        $this->exchangeRetry = "teste.{$this->sufixo}.retry";
        $this->fila = "teste.{$this->sufixo}.comandos";

        $this->admin = new AMQPStreamConnection($host, $porta, $usuario, $senha);
        $this->canal = $this->admin->channel();
        $this->declararTopologia();

        $this->broker = new RabbitMqBroker([
            'host' => $host,
            'port' => $porta,
            'vhost' => '/',
            'user' => $usuario,
            'password' => $senha,
            'heartbeat' => 30,
            'timeout_confirmacao' => 5,
            'prefetch' => 10,
        ], $this->exchangeRetry);
    }

    protected function tearDown(): void
    {
        if (! isset($this->canal)) {
            return;
        }

        $this->broker->fechar();
        foreach ([$this->fila, "{$this->fila}.retry", "{$this->fila}.dlq"] as $fila) {
            $this->canal->queue_delete($fila);
        }
        foreach ([$this->exchange, $this->exchangeRetry, "{$this->exchange}.dlx"] as $exchange) {
            $this->canal->exchange_delete($exchange);
        }
        $this->canal->close();
        $this->admin->close();
    }

    private function declararTopologia(): void
    {
        $dlx = "{$this->exchange}.dlx";
        $this->canal->exchange_declare($this->exchange, 'topic', false, false, false);
        $this->canal->exchange_declare($this->exchangeRetry, 'direct', false, false, false);
        $this->canal->exchange_declare($dlx, 'direct', false, false, false);

        $this->canal->queue_declare($this->fila, false, false, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $dlx,
            'x-dead-letter-routing-key' => $this->fila,
        ]));
        $this->canal->queue_bind($this->fila, $this->exchange, 'billing.cmd.#');

        $this->canal->queue_declare("{$this->fila}.retry", false, false, false, false, false, new AMQPTable([
            'x-message-ttl' => 200,
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => $this->fila,
        ]));
        $this->canal->queue_bind("{$this->fila}.retry", $this->exchangeRetry, $this->fila);

        $this->canal->queue_declare("{$this->fila}.dlq", false, false, false, false);
        $this->canal->queue_bind("{$this->fila}.dlq", $dlx, $this->fila);
    }

    private function retirar(string $fila): ?AMQPMessage
    {
        for ($i = 0; $i < 30; $i++) {
            $mensagem = $this->canal->basic_get($fila, true);
            if ($mensagem !== null) {
                return $mensagem;
            }
            usleep(100_000);
        }

        return null;
    }

    private function consumirUma(callable $tratar): void
    {
        $tratadas = 0;
        $this->broker->consumir(
            $this->fila,
            function (string $corpo, int $tentativas) use ($tratar, &$tratadas) {
                $tratadas++;

                return $tratar($corpo, $tentativas);
            },
            function () use (&$tratadas) {
                return $tratadas === 0;
            },
        );
    }

    public function test_publica_com_confirmacao_e_cabecalhos(): void
    {
        $this->broker->publicar($this->exchange, 'billing.cmd.cobrar', '{"a":1}', [
            'x-saga-id' => 'saga-1',
            'message_id' => 'msg-1',
        ]);

        $mensagem = $this->retirar($this->fila);
        $this->assertNotNull($mensagem);
        $this->assertSame('{"a":1}', $mensagem->getBody());
        $this->assertSame('msg-1', $mensagem->get('message_id'));
        $this->assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $mensagem->get('delivery_mode'));
        $this->assertSame(['x-saga-id' => 'saga-1'], $mensagem->get('application_headers')->getNativeData());
    }

    public function test_mensagem_sem_rota_falha_em_vez_de_sumir(): void
    {
        $this->expectException(FalhaAoPublicar::class);

        $this->broker->publicar($this->exchange, 'execucao.cmd.enfileirar', '{}');
    }

    public function test_confirmar_remove_da_fila(): void
    {
        $this->broker->publicar($this->exchange, 'billing.cmd.cobrar', '{"ok":true}');
        $recebido = null;

        $this->consumirUma(function (string $corpo, int $tentativas) use (&$recebido) {
            $recebido = [$corpo, $tentativas];

            return Decisao::Confirmar;
        });

        $this->assertSame(['{"ok":true}', 0], $recebido);
        $this->assertNull($this->canal->basic_get($this->fila, true));
    }

    public function test_rejeitar_envia_para_a_dlq(): void
    {
        $this->broker->publicar($this->exchange, 'billing.cmd.cobrar', '{"ruim":true}');

        $this->consumirUma(fn () => Decisao::Rejeitar);

        $this->assertSame('{"ruim":true}', $this->retirar("{$this->fila}.dlq")?->getBody());
    }

    public function test_retentar_devolve_a_mensagem_com_tentativa_incrementada(): void
    {
        $this->broker->publicar($this->exchange, 'billing.cmd.cobrar', '{"x":1}', [
            'x-saga-id' => 'saga-1',
            'message_id' => 'msg-1',
        ]);

        $this->consumirUma(fn () => Decisao::Retentar);
        $tentativas = null;
        $this->consumirUma(function (string $corpo, int $t) use (&$tentativas) {
            $tentativas = $t;

            return Decisao::Confirmar;
        });

        $this->assertSame(1, $tentativas);
        $this->assertNull($this->canal->basic_get("{$this->fila}.dlq", true));
    }
}
