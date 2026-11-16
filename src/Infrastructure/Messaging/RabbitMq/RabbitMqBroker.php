<?php

namespace Infrastructure\Messaging\RabbitMq;

use Infrastructure\Messaging\Broker;
use Infrastructure\Messaging\Decisao;
use Infrastructure\Messaging\FalhaAoPublicar;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMqBroker implements Broker
{
    private ?AbstractConnection $conexao = null;
    private ?AMQPChannel $canal = null;
    private bool $recusada = false;
    private bool $devolvida = false;

    public function __construct(
        private array $config,
        private string $exchangeRetry,
    ) {}

    public function publicar(string $exchange, string $routingKey, string $corpo, array $cabecalhos = []): void
    {
        $canal = $this->canal();
        $this->recusada = false;
        $this->devolvida = false;

        $propriedades = [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => new AMQPTable(array_diff_key($cabecalhos, ['message_id' => true])),
        ];
        if (isset($cabecalhos['message_id'])) {
            $propriedades['message_id'] = $cabecalhos['message_id'];
        }

        $canal->basic_publish(new AMQPMessage($corpo, $propriedades), $exchange, $routingKey, true);
        $canal->wait_for_pending_acks_returns($this->config['timeout_confirmacao']);

        if ($this->devolvida) {
            throw new FalhaAoPublicar("Mensagem sem rota no exchange {$exchange} para {$routingKey}.");
        }
        if ($this->recusada) {
            throw new FalhaAoPublicar("Broker recusou a mensagem para {$routingKey}.");
        }
    }

    public function consumir(string $fila, callable $tratar, callable $continuar): void
    {
        $canal = $this->canal();
        $canal->basic_qos(0, $this->config['prefetch'], false);

        $consumidor = $canal->basic_consume($fila, '', false, false, false, false, function (AMQPMessage $mensagem) use ($fila, $tratar) {
            $cabecalhos = $mensagem->has('application_headers')
                ? $mensagem->get('application_headers')->getNativeData()
                : [];
            $tentativas = (int) ($cabecalhos['x-tentativas'] ?? 0);

            match ($tratar($mensagem->getBody(), $tentativas)) {
                Decisao::Confirmar => $mensagem->ack(),
                Decisao::Rejeitar => $mensagem->nack(false),
                Decisao::Retentar => $this->retentar($mensagem, $fila, $cabecalhos, $tentativas),
            };
        });

        try {
            while ($canal->is_consuming() && $continuar()) {
                try {
                    $canal->wait(null, false, 1);
                } catch (AMQPTimeoutException) {
                }
            }
        } finally {
            $canal->basic_cancel($consumidor);
        }
    }

    public function fechar(): void
    {
        $this->canal?->close();
        $this->conexao?->close();
        $this->canal = null;
        $this->conexao = null;
    }

    private function retentar(AMQPMessage $mensagem, string $fila, array $cabecalhos, int $tentativas): void
    {
        $cabecalhos['x-tentativas'] = $tentativas + 1;
        if ($mensagem->has('message_id')) {
            $cabecalhos['message_id'] = $mensagem->get('message_id');
        }

        $this->publicar($this->exchangeRetry, $fila, $mensagem->getBody(), $cabecalhos);
        $mensagem->ack();
    }

    private function canal(): AMQPChannel
    {
        if ($this->canal !== null) {
            return $this->canal;
        }

        $config = new AMQPConnectionConfig();
        $config->setHost($this->config['host']);
        $config->setPort($this->config['port']);
        $config->setUser($this->config['user']);
        $config->setPassword($this->config['password']);
        $config->setVhost($this->config['vhost']);
        $config->setHeartbeat($this->config['heartbeat']);
        $config->setReadTimeout(max(3, $this->config['heartbeat'] * 2));
        $config->setWriteTimeout(max(3, $this->config['heartbeat'] * 2));

        $this->conexao = AMQPConnectionFactory::create($config);
        $this->canal = $this->conexao->channel();
        $this->canal->confirm_select();
        $this->canal->set_nack_handler(function () {
            $this->recusada = true;
        });
        $this->canal->set_return_listener(function () {
            $this->devolvida = true;
        });

        return $this->canal;
    }
}
