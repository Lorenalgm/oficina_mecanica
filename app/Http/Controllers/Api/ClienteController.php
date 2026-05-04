<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use Application\Cliente\UseCases\AtualizarCliente;
use Application\Cliente\UseCases\BuscarCliente;
use Application\Cliente\UseCases\CriarCliente;
use Application\Cliente\UseCases\ListarClientes;
use Application\Cliente\UseCases\RemoverCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClienteController extends Controller
{
    public function __construct(
        private CriarCliente $criarCliente,
        private ListarClientes $listarClientes,
        private BuscarCliente $buscarCliente,
        private AtualizarCliente $atualizarCliente,
        private RemoverCliente $removerCliente,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return ClienteResource::collection($this->listarClientes->executar());
    }


    public function store(StoreClienteRequest $request): JsonResponse
    {
        $cliente = $this->criarCliente->executar(
            nome: $request->nome,
            documento: $request->documento,
            celular: $request->celular,
            email: $request->email,
        );

        return (new ClienteResource($cliente))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $cliente = $this->buscarCliente->executar($id);

        if (!$cliente) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        return (new ClienteResource($cliente))->response();
    }

    public function update(UpdateClienteRequest $request, int $id): JsonResponse
    {
        $cliente = $this->atualizarCliente->executar(
            id: $id,
            nome: $request->nome,
            celular: $request->celular,
            email: $request->email,
        );

        return (new ClienteResource($cliente))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $this->removerCliente->executar($id);

        return response()->json(null, 204);
    }
}
