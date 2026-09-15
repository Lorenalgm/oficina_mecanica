<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida o JWT emitido pela Lambda de autenticação.
 *
 * O API Gateway já barra tokens inválidos pelo Lambda Authorizer; repetimos a
 * verificação aqui como defesa em profundidade e para que a API continue
 * protegida quando executada fora do Gateway (kind local, testes).
 */
class ValidarJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token de autenticação não informado.'], 401);
        }

        $secret = config('jwt.secret');

        if (! $secret) {
            return response()->json(['message' => 'Autenticação não configurada.'], 500);
        }

        try {
            JWT::$leeway = config('jwt.leeway');
            $payload = JWT::decode($token, new Key($secret, config('jwt.algo')));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Token inválido ou expirado.'], 401);
        }

        if (($payload->iss ?? null) !== config('jwt.issuer')) {
            return response()->json(['message' => 'Token inválido ou expirado.'], 401);
        }

        $request->attributes->set('cliente_id', $payload->sub ?? null);
        $request->attributes->set('cpf', $payload->cpf ?? null);

        return $next($request);
    }
}
