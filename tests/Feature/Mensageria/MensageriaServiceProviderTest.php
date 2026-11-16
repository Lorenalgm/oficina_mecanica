<?php

namespace Tests\Feature\Mensageria;

use Domain\Shared\Messaging\Envelope;
use Domain\Shared\Messaging\MessageHandler;
use Domain\Shared\Messaging\MessagePublisher;
use Infrastructure\Messaging\Broker;
use Infrastructure\Messaging\HandlerRegistry;
use Infrastructure\Messaging\OutboxPublisher;
use Infrastructure\Messaging\RabbitMq\RabbitMqBroker;
use Tests\TestCase;

class MensageriaServiceProviderTest extends TestCase
{
    public function test_publisher_do_dominio_grava_na_outbox(): void
    {
        $this->assertInstanceOf(OutboxPublisher::class, app(MessagePublisher::class));
    }

    public function test_broker_e_o_rabbitmq_e_unico_por_processo(): void
    {
        $this->assertInstanceOf(RabbitMqBroker::class, app(Broker::class));
        $this->assertSame(app(Broker::class), app(Broker::class));
    }

    public function test_registro_resolve_handlers_da_configuracao_pelo_container(): void
    {
        config(['mensageria.handlers' => ['billing.evt.orcamento-gerado' => HandlerDeConfiguracao::class]]);

        $registro = app(HandlerRegistry::class);

        $this->assertInstanceOf(HandlerDeConfiguracao::class, $registro->para('billing.evt.orcamento-gerado'));
        $this->assertNull($registro->para('execucao.evt.finalizada'));
    }
}

class HandlerDeConfiguracao implements MessageHandler
{
    public function tratar(Envelope $envelope): void
    {
    }
}
