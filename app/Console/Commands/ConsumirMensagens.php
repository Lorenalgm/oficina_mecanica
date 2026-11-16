<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Infrastructure\Messaging\Broker;
use Infrastructure\Messaging\ProcessadorDeMensagens;

class ConsumirMensagens extends Command
{
    protected $signature = 'mensageria:consumir';

    protected $description = 'Consome a fila do serviço no RabbitMQ até receber SIGTERM';

    private bool $parar = false;

    public function handle(Broker $broker, ProcessadorDeMensagens $processador): int
    {
        $this->trap([SIGTERM, SIGINT], fn () => $this->parar = true);

        $broker->consumir(
            config('mensageria.fila'),
            fn (string $corpo, int $tentativas) => $processador->processar($corpo, $tentativas),
            function () {
                touch(config('mensageria.heartbeat_arquivo'));

                return ! $this->parar;
            },
        );

        return self::SUCCESS;
    }
}
