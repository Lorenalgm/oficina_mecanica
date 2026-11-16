<?php

namespace Domain\Shared\Messaging;

interface MessageHandler
{
    public function tratar(Envelope $envelope): void;
}
