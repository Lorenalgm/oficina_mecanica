<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Repositories\OSRepository;

class ConsultarOSPublica
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(string $documento, string $placa): ?array
    {
        $os = $this->repositorio->findPublica($documento, $placa);

        if (!$os) {
            return null;
        }

        return [
            'status_atual' => $os->getStatusAtualNome(),
            'descricao_problema' => $os->getDescricaoProblema(),
        ];
    }
}
