<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreVeiculoRequest;
use App\Http\Requests\UpdateVeiculoRequest;
use App\Http\Resources\VeiculoResource;
use Application\Veiculo\UseCases\AtualizarVeiculo;
use Application\Veiculo\UseCases\BuscarVeiculo;
use Application\Veiculo\UseCases\CriarVeiculo;
use Application\Veiculo\UseCases\ListarVeiculos;
use Application\Veiculo\UseCases\RemoverVeiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VeiculoController extends Controller
{
    public function __construct(
        private CriarVeiculo $criarVeiculo,
        private ListarVeiculos $listarVeiculos,
        private BuscarVeiculo $buscarVeiculo,
        private AtualizarVeiculo $atualizarVeiculo,
        private RemoverVeiculo $removerVeiculo,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return VeiculoResource::collection($this->listarVeiculos->executar());
    }

    public function store(StoreVeiculoRequest $request): JsonResponse
    {
        $veiculo = $this->criarVeiculo->executar(
            placa: $request->placa,
            marca: $request->marca,
            modelo: $request->modelo,
            ano: (int) $request->ano,
            clienteId: (int) $request->cliente_id,
        );

        return (new VeiculoResource($veiculo))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $veiculo = $this->buscarVeiculo->executar($id);

        if (!$veiculo) {
            return response()->json(['message' => 'Veículo não encontrado.'], 404);
        }

        return (new VeiculoResource($veiculo))->response();
    }

    public function update(UpdateVeiculoRequest $request, int $id): JsonResponse
    {
        try {
            $veiculo = $this->atualizarVeiculo->executar(
                id: $id,
                marca: $request->marca,
                modelo: $request->modelo,
                ano: (int) $request->ano,
            );
            return (new VeiculoResource($veiculo))->response();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $this->removerVeiculo->executar($id);

        return response()->json(null, 204);
    }
}
