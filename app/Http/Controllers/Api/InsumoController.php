<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreInsumoRequest;
use App\Http\Requests\UpdateInsumoRequest;
use App\Http\Resources\InsumoResource;
use Application\Insumo\UseCases\AtualizarInsumo;
use Application\Insumo\UseCases\BuscarInsumo;
use Application\Insumo\UseCases\CriarInsumo;
use Application\Insumo\UseCases\ListarInsumos;
use Application\Insumo\UseCases\RemoverInsumo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InsumoController extends Controller
{
    public function __construct(
        private CriarInsumo $criarInsumo,
        private ListarInsumos $listarInsumos,
        private BuscarInsumo $buscarInsumo,
        private AtualizarInsumo $atualizarInsumo,
        private RemoverInsumo $removerInsumo,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $insumos = $this->listarInsumos->executar();

        return InsumoResource::collection($insumos);
    }

    public function store(StoreInsumoRequest $request): JsonResponse
    {
        $insumo = $this->criarInsumo->executar(
            nome: $request->nome,
            valor: (float) $request->valor,
            quantidadeEstoque: (int) $request->quantidade_estoque,
        );

        return (new InsumoResource($insumo))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $insumo = $this->buscarInsumo->executar($id);

        if (!$insumo) {
            return response()->json(['message' => 'Insumo não encontrado.'], 404);
        }

        return (new InsumoResource($insumo))->response();
    }

    public function update(UpdateInsumoRequest $request, int $id): JsonResponse
    {
        $insumo = $this->atualizarInsumo->executar(
            id: $id,
            nome: $request->nome,
            valor: (float) $request->valor,
            quantidadeEstoque: (int) $request->quantidade_estoque,
        );

        return (new InsumoResource($insumo))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $this->removerInsumo->executar($id);

        return response()->json(null, 204);
    }
}
