<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Repositories\OSRepository;
use RuntimeException;

class AdicionarServicoNaOS
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(int $osId, int $servicoId): OSServico
    {
        if (!$this->repositorio->osExiste($osId)) {
            throw new RuntimeException("OS #{$osId} não encontrada.");
        }

        if (!$this->repositorio->servicoExiste($servicoId)) {
            throw new RuntimeException("Serviço #{$servicoId} não encontrado.");
        }

        return $this->repositorio->adicionarServico($osId, $servicoId);
    }
}
