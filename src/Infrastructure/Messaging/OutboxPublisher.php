<?php

namespace Infrastructure\Messaging;

use Domain\Shared\Messaging\Envelope;
use Domain\Shared\Messaging\MessagePublisher;
use Illuminate\Support\Facades\DB;

class OutboxPublisher implements MessagePublisher
{
    public function publicar(Envelope $envelope): void
    {
        DB::table('outbox')->insert([
            'id' => $envelope->messageId,
            'type' => $envelope->type,
            'saga_id' => $envelope->sagaId,
            'corpo' => $envelope->paraJson(),
            'criado_em' => now(),
            'publicado_em' => null,
        ]);
    }
}
