<?php

namespace Application\Saga\UseCases;

use Domain\Atendimento\Saga\SagaOS;
use Domain\Atendimento\Saga\SagaRepository;

class ConsultarSagaDaOS
{
    public function __construct(private SagaRepository $sagas) {}

    /**
     * @return array{saga: SagaOS, passos: list<array{passo: string, detalhes: array, registrado_em: \DateTimeInterface}>}|null
     */
    public function executar(int $osId): ?array
    {
        $saga = $this->sagas->buscarUltimaDaOS($osId);

        return $saga === null ? null : ['saga' => $saga, 'passos' => $this->sagas->passos($saga->id)];
    }
}
