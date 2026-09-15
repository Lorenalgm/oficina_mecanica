<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emite um registro estruturado por requisição.
 *
 * É a fonte da latência das APIs no New Relic: o campo duration_ms alimenta os
 * painéis de p95/p99 por rota, já que não usamos agente APM de PHP
 * (ver docs/adrs/ADR-003-observabilidade-via-logs-estruturados.md).
 */
class LogRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);

        $response = $next($request);

        $duracaoMs = round((microtime(true) - $inicio) * 1000, 2);
        $status = $response->getStatusCode();

        Log::log($status >= 500 ? 'error' : 'info', 'http_request', [
            'event_type' => 'http_request',
            'method' => $request->method(),
            'route' => $request->route()?->uri() ?? $request->path(),
            'path' => $request->path(),
            'status' => $status,
            'duration_ms' => $duracaoMs,
            'cliente_id' => $request->attributes->get('cliente_id'),
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
