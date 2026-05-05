<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreServicoRequest;
use App\Http\Requests\UpdateServicoRequest;
use App\Http\Resources\ServicoResource;
use Application\Servico\UseCases\AtualizarServico;
use Application\Servico\UseCases\BuscarServico;
use Application\Servico\UseCases\CriarServico;
use Application\Servico\UseCases\ListarServicos;
use Application\Servico\UseCases\RemoverServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServicoController extends Controller
{
    public function __construct(
        private CriarServico $criarServico,
        private ListarServicos $listarServicos,
        private BuscarServico $buscarServico,
        private AtualizarServico $atualizarServico,
        private RemoverServico $removerServico,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $servicos = $this->listarServicos->executar();

        return ServicoResource::collection($servicos);
    }

    public function store(StoreServicoRequest $request): JsonResponse
    {
        $servico = $this->criarServico->executar(
            nome: $request->nome,
            valor: (float) $request->valor,
        );

        return (new ServicoResource($servico))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $servico = $this->buscarServico->executar($id);

        if (!$servico) {
            return response()->json(['message' => 'Serviço não encontrado.'], 404);
        }

        return (new ServicoResource($servico))->response();
    }

    public function update(UpdateServicoRequest $request, int $id): JsonResponse
    {
        try {
            $servico = $this->atualizarServico->executar(
                id: $id,
                nome: $request->nome,
                valor: (float) $request->valor,
            );
            return (new ServicoResource($servico))->response();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $this->removerServico->executar($id);

        return response()->json(null, 204);
    }
}
