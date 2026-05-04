<?php

namespace Application\OS\UseCases;

use App\Models\OS as OSModel;
use App\Models\OSStatus as OSStatusModel;
use App\Models\Status;
use Domain\Atendimento\Repositories\OSRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AlterarStatusOS
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(int $osId, int $statusId): void
    {
        if (!Status::find($statusId)) {
            throw new RuntimeException("Status #{$statusId} não encontrado.");
        }

        DB::transaction(function () use ($osId, $statusId) {
            $os = OSModel::findOrFail($osId);
            $os->status_atual_id = $statusId;
            $os->save();

            OSStatusModel::create([
                'os_id' => $osId,
                'status_id' => $statusId,
                'data_status' => now(),
            ]);
        });
    }
}
