<?php

namespace Application\OS\UseCases;

use App\Models\OSStatus;
use App\Models\Status;

class CalcularTempoMedio
{
    public function executar(): ?float
    {
        $statusEmExecucao = Status::where('nome', 'Em execução')->first();
        $statusFinalizada = Status::where('nome', 'Finalizada')->first();

        if (!$statusEmExecucao || !$statusFinalizada) {
            return null;
        }

        $pares = OSStatus::where('os_status.status_id', $statusEmExecucao->id)
            ->join('os_status as s_fin', function ($join) use ($statusFinalizada) {
                $join->on('os_status.os_id', '=', 's_fin.os_id')
                     ->where('s_fin.status_id', $statusFinalizada->id);
            })
            ->selectRaw('os_status.data_status as inicio, s_fin.data_status as fim')
            ->get();

        if ($pares->isEmpty()) {
            return null;
        }

        $totalMinutos = $pares->sum(function ($par) {
            return (\Carbon\Carbon::parse($par->fim)->timestamp - \Carbon\Carbon::parse($par->inicio)->timestamp) / 60;
        });

        return $totalMinutos / $pares->count();
    }
}
