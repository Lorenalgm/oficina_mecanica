<?php

namespace Application\Saga;

use Domain\Atendimento\Events\StatusOSAlterado;
use Domain\Atendimento\Repositories\OSRepository;
use Domain\Atendimento\Saga\SagaOS;
use Domain\Shared\Events\DomainEventDispatcher;
use RuntimeException;

class SincronizadorDeStatus
{
    public function __construct(
        private OSRepository $repositorio,
        private DomainEventDispatcher $eventos,
    ) {}

    public function aplicar(SagaOS $saga): void
    {
        $status = $saga->estado()->statusDaOS();
        if ($status !== null) {
            $this->definir($saga->osId, $status);
        }
    }

    public function definir(int $osId, string $nomeStatus): void
    {
        $statusId = $this->repositorio->findStatusIdByNome($nomeStatus)
            ?? throw new RuntimeException("Status \"{$nomeStatus}\" não configurado.");

        $os = $this->repositorio->findById($osId)
            ?? throw new RuntimeException("OS #{$osId} não encontrada.");

        $statusAnterior = $os->getStatusAtualId();
        if ($statusAnterior === $statusId) {
            return;
        }

        $os->alterarStatus($statusId);
        $this->repositorio->registrarStatus($osId, $statusId);
        $this->eventos->dispatch(new StatusOSAlterado($os, $statusAnterior, $statusId));
    }
}
