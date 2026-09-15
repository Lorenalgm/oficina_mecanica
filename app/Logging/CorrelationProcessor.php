<?php

namespace App\Logging;

use App\Support\CorrelationContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Injeta em todo registro de log os campos que permitem correlacionar
 * requisições no New Relic: correlation_id, serviço e ambiente.
 */
class CorrelationProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: array_merge($record->extra, [
            'correlation_id' => CorrelationContext::get(),
            'service' => config('app.name'),
            'env' => config('app.env'),
        ]));
    }
}
