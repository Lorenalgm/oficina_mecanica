<?php

namespace Domain\Shared\Messaging;

interface MessagePublisher
{
    public function publicar(Envelope $envelope): void;
}
