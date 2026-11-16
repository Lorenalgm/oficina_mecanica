<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Infrastructure\Messaging\OutboxRelay;

class PublicarOutbox extends Command
{
    protected $signature = 'outbox:publicar {--loop : Continua publicando até receber SIGTERM}';

    protected $description = 'Publica no RabbitMQ as mensagens pendentes da outbox';

    private bool $parar = false;

    public function handle(OutboxRelay $relay): int
    {
        $lote = config('mensageria.outbox.lote');
        $intervalo = config('mensageria.outbox.intervalo_ms') * 1000;

        if (! $this->option('loop')) {
            $this->info($relay->publicarPendentes($lote).' mensagem(ns) publicada(s).');

            return self::SUCCESS;
        }

        $this->trap([SIGTERM, SIGINT], fn () => $this->parar = true);

        while (! $this->parar) {
            $publicadas = $relay->publicarPendentes($lote);
            touch(config('mensageria.heartbeat_arquivo'));

            if ($publicadas < $lote) {
                usleep($intervalo);
            }
        }

        return self::SUCCESS;
    }
}
