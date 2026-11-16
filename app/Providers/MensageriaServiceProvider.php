<?php

namespace App\Providers;

use Domain\Shared\Messaging\MessagePublisher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Messaging\Broker;
use Infrastructure\Messaging\HandlerRegistry;
use Infrastructure\Messaging\OutboxPublisher;
use Infrastructure\Messaging\OutboxRelay;
use Infrastructure\Messaging\ProcessadorDeMensagens;
use Infrastructure\Messaging\RabbitMq\RabbitMqBroker;

class MensageriaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MessagePublisher::class, OutboxPublisher::class);

        $this->app->singleton(Broker::class, fn () => new RabbitMqBroker(
            config('mensageria.rabbitmq'),
            config('mensageria.exchange_retry'),
        ));

        $this->app->bind(OutboxRelay::class, fn (Application $app) => new OutboxRelay(
            $app->make(Broker::class),
            config('mensageria.exchange'),
        ));

        $this->app->bind(HandlerRegistry::class, fn (Application $app) => new HandlerRegistry(
            config('mensageria.handlers'),
            fn (string $classe) => $app->make($classe),
        ));

        $this->app->bind(ProcessadorDeMensagens::class, fn (Application $app) => new ProcessadorDeMensagens(
            $app->make(HandlerRegistry::class),
            config('mensageria.max_tentativas'),
        ));
    }
}
