<?php

namespace Application\Saga;

use Domain\Atendimento\Saga\SagaOS;
use Domain\Atendimento\Saga\SagaRepository;
use Domain\Shared\Messaging\Envelope;
use Domain\Shared\Messaging\MessagePublisher;

class ComandosDaSaga
{
    public function __construct(
        private MessagePublisher $publisher,
        private SagaRepository $sagas,
    ) {}

    public function enviar(SagaOS $saga, string $type, array $payload): void
    {
        $envelope = Envelope::criar($type, $saga->id, $saga->correlationId, $saga->osId, $payload);

        $this->publisher->publicar($envelope);
        $saga->registrarComando($envelope->messageId);
        $this->sagas->registrarPasso($saga->id, $type, $payload);
    }
}
