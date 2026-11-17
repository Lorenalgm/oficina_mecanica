<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Events\StatusOSAlterado;
use Domain\Atendimento\Repositories\OSRepository;
use Domain\Atendimento\Saga\SagaJaAtiva;
use Domain\Atendimento\Saga\SagaRepository;
use Domain\Shared\Events\DomainEventDispatcher;
use RuntimeException;

class AlterarStatusOS
{
    public function __construct(
        private OSRepository $repositorio,
        private DomainEventDispatcher $eventos,
        private SagaRepository $sagas,
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

        if ($ativa = $this->sagas->buscarAtivaDaOS($osId)) {
            throw new SagaJaAtiva($ativa);
        }

        $statusAnterior = $os->getStatusAtualId();
        $os->alterarStatus($statusId);
        $this->repositorio->registrarStatus($osId, $statusId);

        $this->eventos->dispatch(new StatusOSAlterado($os, $statusAnterior, $statusId));
    }
}
