<?php

namespace Tests\Concerns;

use Firebase\JWT\JWT;

/**
 * Emite tokens equivalentes aos gerados pela Lambda de autenticação
 * (repositório oficina-auth-lambda), para os testes das rotas protegidas.
 */
trait AutenticaComJwt
{
    protected function jwtToken(array $claims = []): string
    {
        $agora = time();

        return JWT::encode(array_merge([
            'iss' => config('jwt.issuer'),
            'sub' => '1',
            'cpf' => '52998224725',
            'nome' => 'Cliente de Teste',
            'iat' => $agora,
            'exp' => $agora + 3600,
        ], $claims), config('jwt.secret'), config('jwt.algo'));
    }

    protected function cabecalhoJwt(array $claims = []): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtToken($claims)];
    }
}
