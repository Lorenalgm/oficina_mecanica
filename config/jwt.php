<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Segredo compartilhado
    |--------------------------------------------------------------------------
    |
    | Mesmo segredo usado pela Lambda de autenticação (repositório
    | oficina-auth-lambda) para assinar os tokens. Em produção vem do AWS
    | Secrets Manager, injetado no Secret do Kubernetes.
    |
    */

    'secret' => env('JWT_SECRET'),

    'algo' => env('JWT_ALGO', 'HS256'),

    'issuer' => env('JWT_ISSUER', 'oficina-auth'),

    /*
    | Tolerância de relógio, em segundos, entre a Lambda e o cluster.
    */
    'leeway' => (int) env('JWT_LEEWAY', 30),

];
