<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // CorrelationId vem primeiro para que todo log emitido daqui em diante,
        // inclusive o da própria requisição, já carregue o identificador.
        $middleware->api(prepend: [
            \App\Http\Middleware\CorrelationId::class,
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\LogRequest::class,
        ]);

        $middleware->alias([
            'jwt' => \App\Http\Middleware\ValidarJwt::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            // Falhas originadas no contexto de Atendimento significam ordem de
            // serviço que não completou o processamento: é o evento que dispara
            // o alerta "Falha no processamento de OS" no New Relic.
            $eventType = str_starts_with($e::class, 'Domain\\Atendimento')
                ? 'os_processing_failure'
                : 'unhandled_exception';

            Log::error($e->getMessage(), [
                'event_type' => $eventType,
                'exception_class' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        });
    })->create();
