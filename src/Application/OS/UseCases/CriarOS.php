<?php

namespace Application\OS\UseCases;

use App\Models\OS as OSModel;
use App\Models\OSStatus as OSStatusModel;
use App\Models\Status;
use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Repositories\OSRepository;

class CriarOS
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(int $veiculoId, int $clienteId, string $descricaoProblema): OS
    {
        $statusRecebida = Status::where('nome', 'Recebida')->firstOrFail();

        $os = new OS(
            id: null,
            clienteId: $clienteId,
            veiculoId: $veiculoId,
            statusAtualId: $statusRecebida->id,
            descricaoProblema: $descricaoProblema,
        );

        $os = $this->repositorio->save($os);

        OSStatusModel::create([
            'os_id' => $os->getId(),
            'status_id' => $statusRecebida->id,
            'data_status' => now(),
        ]);

        return $os;
    }
}
