<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Repositories\OSRepository;
use RuntimeException;

class CriarOS
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(int $veiculoId, int $clienteId, string $descricaoProblema): OS
    {
        $statusId = $this->repositorio->findStatusIdByNome('Recebida');

        if ($statusId === null) {
            throw new RuntimeException('Status "Recebida" não configurado.');
        }

        $os = new OS(
            id: null,
            clienteId: $clienteId,
            veiculoId: $veiculoId,
            statusAtualId: $statusId,
            descricaoProblema: $descricaoProblema,
        );

        $os = $this->repositorio->save($os);

        $this->repositorio->registrarStatus($os->getId(), $statusId);

        return $os;
    }
}
