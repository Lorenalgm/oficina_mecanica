<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Oficina Mecânica API',
    description: 'API do sistema integrado de atendimento e execução de serviços da oficina mecânica.',
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'token',
)]
abstract class Controller
{
}
