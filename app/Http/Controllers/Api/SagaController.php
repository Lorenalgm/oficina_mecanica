<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SagaResource;
use Application\Saga\UseCases\ConsultarSagaDaOS;
use Illuminate\Http\JsonResponse;

class SagaController extends Controller
{
    public function __construct(private ConsultarSagaDaOS $consultar) {}

    public function show(int $id): JsonResponse
    {
        $resultado = $this->consultar->executar($id);

        if ($resultado === null) {
            return response()->json(['message' => 'A OS não tem Saga.'], 404);
        }

        return (new SagaResource($resultado))->response();
    }
}
