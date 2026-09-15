<?php

namespace App\Listeners;

use Domain\Atendimento\Events\StatusOSAlterado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Emite os eventos estruturados que alimentam os painéis de operação no
 * New Relic (tempo médio por status e volume de mudanças de status).
 *
 * É separado do EnviarEmailStatusOS de propósito: telemetria não deve depender
 * do sucesso da notificação, nem o contrário.
 */
class RegistrarMetricasOS
{
    public function handle(StatusOSAlterado $event): void
    {
        Log::info('os_status_changed', [
            'event_type' => 'os_status_changed',
            'os_id' => (int) $event->os->getId(),
            'status_anterior_id' => $event->statusAnteriorId,
            'status_novo_id' => $event->statusNovoId,
            'duracao_status_min' => $this->minutosNoStatusAnterior((int) $event->os->getId()),
        ]);
    }

    /**
     * Minutos decorridos desde o último registro de status desta OS — ou seja,
     * quanto tempo ela permaneceu no status que está sendo deixado agora.
     */
    private function minutosNoStatusAnterior(int $osId): ?float
    {
        $ultimo = DB::table('os_status')
            ->where('os_id', $osId)
            ->orderByDesc('data_status')
            ->value('data_status');

        if (! $ultimo) {
            return null;
        }

        return round((time() - strtotime((string) $ultimo)) / 60, 2);
    }
}
