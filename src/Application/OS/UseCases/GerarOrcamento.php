<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Entities\OSOrcamento;
use Domain\Atendimento\Repositories\OSRepository;
use RuntimeException;

class GerarOrcamento
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(int $osId): OSOrcamento
    {
        $os = $this->repositorio->findById($osId);

        if (!$os) {
            throw new RuntimeException("OS #{$osId} não encontrada.");
        }

        $valorTotal = $os->calcularValorOrcamento();

        $orcamento = $this->repositorio->criarOrcamento($osId, $valorTotal);

        $statusId = $this->repositorio->findStatusIdByNome('Aguardando aprovação');
        if ($statusId !== null) {
            $this->repositorio->registrarStatus($osId, $statusId);
        }

        return $orcamento;
    }
}
