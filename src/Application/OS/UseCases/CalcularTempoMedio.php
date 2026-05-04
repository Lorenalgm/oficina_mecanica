<?php

namespace Application\OS\UseCases;

use App\Models\OSStatus;
use App\Models\Status;
use Illuminate\Support\Facades\DB;

class CalcularTempoMedio
{
    public function executar(): ?float
    {
        $statusEmExecucao = Status::where('nome', 'Em execução')->first();
        $statusFinalizada = Status::where('nome', 'Finalizada')->first();

        if (!$statusEmExecucao || !$statusFinalizada) {
            return null;
        }

        $resultado = DB::select("
            SELECT AVG(
                (JULIANDAY(s_fin.data_status) - JULIANDAY(s_exec.data_status)) * 24 * 60
            ) as media_minutos
            FROM os_status s_exec
            JOIN os_status s_fin ON s_exec.os_id = s_fin.os_id
            WHERE s_exec.status_id = ?
            AND s_fin.status_id = ?
        ", [$statusEmExecucao->id, $statusFinalizada->id]);

        return $resultado[0]->media_minutos ?? null;
    }
}
