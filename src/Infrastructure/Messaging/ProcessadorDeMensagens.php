<?php

namespace Infrastructure\Messaging;

use App\Support\CorrelationContext;
use Domain\Shared\Messaging\Envelope;
use Domain\Shared\Messaging\MensagemInvalida;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessadorDeMensagens
{
    public function __construct(
        private HandlerRegistry $handlers,
        private int $maxTentativas,
    ) {}

    public function processar(string $corpo, int $tentativas): Decisao
    {
        try {
            $envelope = Envelope::deJson($corpo);
        } catch (MensagemInvalida $e) {
            Log::warning('Mensagem inválida enviada para a DLQ', [
                'event_type' => 'mensageria.mensagem_invalida',
                'erro' => $e->getMessage(),
            ]);

            return Decisao::Rejeitar;
        }

        CorrelationContext::set($envelope->correlationId);

        try {
            return $this->processarEnvelope($envelope, $tentativas);
        } finally {
            CorrelationContext::clear();
        }
    }

    private function processarEnvelope(Envelope $envelope, int $tentativas): Decisao
    {
        $contexto = [
            'message_id' => $envelope->messageId,
            'message_type' => $envelope->type,
            'saga_id' => $envelope->sagaId,
            'os_id' => $envelope->osId,
        ];

        $handler = $this->handlers->para($envelope->type);
        if ($handler === null) {
            Log::error('Tipo de mensagem sem handler enviado para a DLQ', $contexto + [
                'event_type' => 'mensageria.tipo_desconhecido',
            ]);

            return Decisao::Rejeitar;
        }

        if ($this->jaProcessada($envelope->messageId)) {
            Log::info('Mensagem duplicada ignorada', $contexto + ['event_type' => 'mensageria.duplicada']);

            return Decisao::Confirmar;
        }

        try {
            DB::transaction(function () use ($envelope, $handler) {
                DB::table('mensagens_processadas')->insert([
                    'message_id' => $envelope->messageId,
                    'type' => $envelope->type,
                    'saga_id' => $envelope->sagaId,
                    'processado_em' => now(),
                ]);

                $handler->tratar($envelope);
            });
        } catch (UniqueConstraintViolationException $e) {
            if (! $this->jaProcessada($envelope->messageId)) {
                return $this->falhou($e, $tentativas, $contexto);
            }

            Log::info('Mensagem duplicada ignorada', $contexto + ['event_type' => 'mensageria.duplicada']);

            return Decisao::Confirmar;
        } catch (Throwable $e) {
            return $this->falhou($e, $tentativas, $contexto);
        }

        Log::info('Mensagem processada', $contexto + ['event_type' => 'mensageria.processada']);

        return Decisao::Confirmar;
    }

    private function falhou(Throwable $e, int $tentativas, array $contexto): Decisao
    {
        $tentativa = $tentativas + 1;
        $esgotou = $tentativa >= $this->maxTentativas;

        Log::log($esgotou ? 'error' : 'warning', 'Falha ao processar mensagem', $contexto + [
            'event_type' => $esgotou ? 'mensageria.enviada_dlq' : 'mensageria.retentativa',
            'tentativa' => $tentativa,
            'max_tentativas' => $this->maxTentativas,
            'exception_class' => $e::class,
            'erro' => $e->getMessage(),
        ]);

        return $esgotou ? Decisao::Rejeitar : Decisao::Retentar;
    }

    private function jaProcessada(string $messageId): bool
    {
        return DB::table('mensagens_processadas')->where('message_id', $messageId)->exists();
    }
}
