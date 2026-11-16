<?php

namespace App\Console\Commands;

use Domain\Atendimento\Saga\SagaRepository;
use Illuminate\Console\Command;
use Infrastructure\Messaging\OutboxRelay;

class ReenviarComandoDaSaga extends Command
{
    protected $signature = 'saga:reenviar {saga_id : Saga cujo último comando será publicado de novo}';

    protected $description = 'Recoloca o último comando da Saga na outbox (o destino é idempotente)';

    public function handle(SagaRepository $sagas, OutboxRelay $relay): int
    {
        $saga = $sagas->buscar($this->argument('saga_id'));
        if ($saga === null) {
            $this->error('Saga não encontrada.');

            return self::FAILURE;
        }

        if ($saga->ultimoComandoId() === null || ! $relay->reenfileirar($saga->ultimoComandoId())) {
            $this->error('A Saga não tem comando para reenviar.');

            return self::FAILURE;
        }

        $sagas->registrarPasso($saga->id, 'reenvio_manual', ['message_id' => $saga->ultimoComandoId()]);
        $this->info("Comando {$saga->ultimoComandoId()} recolocado na outbox.");

        return self::SUCCESS;
    }
}
