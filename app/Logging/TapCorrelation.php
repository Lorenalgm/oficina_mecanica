<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Aplica o CorrelationProcessor a canais que não aceitam a chave "processors"
 * na configuração (drivers nativos como single/daily).
 */
class TapCorrelation
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->pushProcessor(new CorrelationProcessor());
        }
    }
}
