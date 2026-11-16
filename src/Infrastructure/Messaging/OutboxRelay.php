<?php

namespace Infrastructure\Messaging;

use Domain\Shared\Messaging\Envelope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OutboxRelay
{
    public function __construct(
        private Broker $broker,
        private string $exchange,
    ) {}

    /**
     * Publica as mensagens pendentes em ordem de criação. Para no primeiro erro
     * para não furar a ordem; as já publicadas no lote ficam marcadas.
     *
     * @return int quantidade publicada
     */
    public function publicarPendentes(int $lote): int
    {
        return DB::transaction(function () use ($lote) {
            $pendentes = DB::table('outbox')
                ->whereNull('publicado_em')
                ->orderBy('id')
                ->limit($lote)
                ->lock('for update skip locked')
                ->get();

            $publicadas = 0;

            foreach ($pendentes as $linha) {
                try {
                    $envelope = Envelope::deJson($linha->corpo);
                    $this->broker->publicar(
                        $this->exchange,
                        $envelope->type,
                        $linha->corpo,
                        $envelope->cabecalhos() + ['message_id' => $envelope->messageId],
                    );
                } catch (Throwable $e) {
                    Log::error('Falha ao publicar mensagem da outbox', [
                        'event_type' => 'mensageria.outbox_falhou',
                        'message_id' => $linha->message_id,
                        'message_type' => $linha->type,
                        'saga_id' => $linha->saga_id,
                        'erro' => $e->getMessage(),
                    ]);
                    break;
                }

                DB::table('outbox')->where('id', $linha->id)->update(['publicado_em' => now()]);
                $publicadas++;
            }

            return $publicadas;
        });
    }

    public function reenfileirar(string $messageId): bool
    {
        return DB::table('outbox')->where('message_id', $messageId)->update(['publicado_em' => null]) > 0;
    }
}
