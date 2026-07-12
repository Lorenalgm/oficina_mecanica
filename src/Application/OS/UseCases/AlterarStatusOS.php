<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Events\StatusOSAlterado;
use Domain\Atendimento\Repositories\OSRepository;
use Domain\Shared\Events\DomainEventDispatcher;
use RuntimeException;

class AlterarStatusOS
{
    public function __construct(
        private OSRepository $repositorio,
        private DomainEventDispatcher $eventos,
    ) {}

    public function executar(int $osId, int $statusId): void
    {
        if (!$this->repositorio->statusExiste($statusId)) {
            throw new RuntimeException("Status #{$statusId} não encontrado.");
        }

        $os = $this->repositorio->findById($osId);
        if (!$os) {
            throw new RuntimeException("OS #{$osId} não encontrada.");
        }

        $statusAnterior = $os->getStatusAtualId();
        $os->alterarStatus($statusId);
        $this->repositorio->registrarStatus($osId, $statusId);

        $this->eventos->dispatch(new StatusOSAlterado($os, $statusAnterior, $statusId));
    }
}
