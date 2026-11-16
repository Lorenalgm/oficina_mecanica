<?php

namespace App\Http\Controllers\Api;

use Application\Cliente\UseCases\BuscarClientePorDocumento;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class InternoController extends Controller
{
    public function __construct(private BuscarClientePorDocumento $buscarCliente) {}

    public function clientePorDocumento(string $documento): JsonResponse
    {
        try {
            $cliente = $this->buscarCliente->executar($documento);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($cliente === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        return response()->json(['data' => ['id' => $cliente->getId(), 'nome' => $cliente->getNome()]]);
    }
}
