<?php

namespace App\Http\Middleware;

use App\Support\CorrelationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Propaga um identificador de correlação por toda a requisição.
 *
 * Reaproveita o X-Request-Id enviado pelo API Gateway/Ingress quando existe,
 * ou deriva do X-Amzn-Trace-Id; caso contrário gera um UUID. O valor volta no
 * header da resposta para que o cliente consiga rastrear a chamada.
 */
class CorrelationId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header(self::HEADER)
            ?? $this->fromAmazonTraceHeader($request)
            ?? (string) Str::uuid();

        CorrelationContext::set($correlationId);
        $request->headers->set(self::HEADER, $correlationId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }

    private function fromAmazonTraceHeader(Request $request): ?string
    {
        $trace = $request->header('X-Amzn-Trace-Id');

        if (! $trace) {
            return null;
        }

        // Formato: "Root=1-5759e988-bd862e3fe1be46a994272793;Parent=..."
        foreach (explode(';', $trace) as $parte) {
            if (str_starts_with($parte, 'Root=')) {
                return substr($parte, 5);
            }
        }

        return $trace;
    }
}
