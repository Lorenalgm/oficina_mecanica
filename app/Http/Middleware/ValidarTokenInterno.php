<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidarTokenInterno
{
    public const HEADER = 'X-Internal-Token';

    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('services.interno.token');
        $recebido = (string) $request->header(self::HEADER);

        if ($esperado === '' || ! hash_equals($esperado, $recebido)) {
            Log::warning('Chamada interna recusada', [
                'event_type' => 'interno.token_invalido',
                'path' => $request->path(),
                'configurado' => $esperado !== '',
            ]);

            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        return $next($request);
    }
}
