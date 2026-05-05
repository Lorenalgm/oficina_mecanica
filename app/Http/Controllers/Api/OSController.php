<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\PatchOSStatusRequest;
use App\Http\Requests\StoreOSRequest;
use App\Http\Requests\StoreOSServicoInsumoRequest;
use App\Http\Requests\StoreOSServicoRequest;
use App\Http\Resources\OSResource;
use Application\OS\UseCases\AdicionarInsumoNoServico;
use Application\OS\UseCases\AdicionarServicoNaOS;
use Application\OS\UseCases\AlterarStatusOS;
use Application\OS\UseCases\AprovarOrcamento;
use Application\OS\UseCases\CalcularTempoMedio;
use Application\OS\UseCases\ConsultarOSPublica;
use Application\OS\UseCases\CriarOS;
use Application\OS\UseCases\DetalharOS;
use Application\OS\UseCases\GerarOrcamento;
use Application\OS\UseCases\ListarOS;
use Application\OS\UseCases\RecusarOrcamento;
use Domain\Catalogo\Exceptions\EstoqueInsuficienteException;
use Infrastructure\Persistence\Eloquent\OSMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OSController extends Controller
{
    public function __construct(
        private CriarOS $criarOS,
        private ListarOS $listarOS,
        private DetalharOS $detalharOS,
        private AdicionarServicoNaOS $adicionarServico,
        private AdicionarInsumoNoServico $adicionarInsumo,
        private GerarOrcamento $gerarOrcamento,
        private AprovarOrcamento $aprovarOrcamento,
        private RecusarOrcamento $recusarOrcamento,
        private AlterarStatusOS $alterarStatus,
        private CalcularTempoMedio $calcularTempoMedio,
        private ConsultarOSPublica $consultarOSPublica,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = array_filter([
            'cliente_id' => $request->query('cliente_id'),
            'status_id' => $request->query('status_id'),
            'veiculo_id' => $request->query('veiculo_id'),
        ]);

        $lista = $this->listarOS->executar($filtros);
        $entities = $lista->map(fn($m) => OSMapper::toEntity($m));

        return OSResource::collection($entities);
    }

    public function store(StoreOSRequest $request): JsonResponse
    {
        $os = $this->criarOS->executar(
            veiculoId: (int) $request->veiculo_id,
            clienteId: (int) $request->cliente_id,
            descricaoProblema: $request->descricao_problema,
        );

        $modelo = $this->detalharOS->executar($os->getId());

        return (new OSResource($modelo))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $os = $this->detalharOS->executar($id);

        if (!$os) {
            return response()->json(['message' => 'OS não encontrada.'], 404);
        }

        return (new OSResource($os))->response();
    }

    public function adicionarServico(StoreOSServicoRequest $request, int $id): JsonResponse
    {
        try {
            $osServico = $this->adicionarServico->executar(
                osId: $id,
                servicoId: (int) $request->servico_id,
            );
            return response()->json(['data' => [
                'id' => $osServico->id,
                'os_id' => $osServico->os_id,
                'servico_id' => $osServico->servico_id,
            ]], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function adicionarInsumo(StoreOSServicoInsumoRequest $request, int $id, int $osServicoId): JsonResponse
    {
        try {
            $item = $this->adicionarInsumo->executar(
                osServicoId: $osServicoId,
                insumoId: (int) $request->insumo_id,
                quantidade: (int) $request->quantidade,
            );
            return response()->json(['data' => [
                'id' => $item->id,
                'os_servico_id' => $item->os_servico_id,
                'insumo_id' => $item->insumo_id,
                'quantidade' => $item->quantidade,
            ]], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function gerarOrcamento(int $id): JsonResponse
    {
        try {
            $orcamento = $this->gerarOrcamento->executar($id);

            return response()->json(['data' => [
                'id' => $orcamento->id,
                'os_id' => $orcamento->os_id,
                'valor_total' => $orcamento->valor_total,
                'status' => $orcamento->status,
                'data_orcamento' => $orcamento->data_orcamento,
            ]], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function aprovarOrcamento(int $id): JsonResponse
    {
        try {
            $orcamento = $this->aprovarOrcamento->executar($id);

            return response()->json(['data' => [
                'id' => $orcamento->id,
                'status' => $orcamento->status,
                'data_aprovacao' => $orcamento->data_aprovacao,
            ]]);
        } catch (EstoqueInsuficienteException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recusarOrcamento(int $id): JsonResponse
    {
        try {
            $orcamento = $this->recusarOrcamento->executar($id);

            return response()->json(['data' => ['id' => $orcamento->id, 'status' => $orcamento->status]]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function alterarStatus(PatchOSStatusRequest $request, int $id): JsonResponse
    {
        try {
            $this->alterarStatus->executar(
                osId: $id,
                statusId: (int) $request->status_id,
            );

            $os = $this->detalharOS->executar($id);

            return (new OSResource($os))->response();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function tempoMedio(): JsonResponse
    {
        $media = $this->calcularTempoMedio->executar();

        return response()->json(['tempo_medio_minutos' => $media]);
    }

    public function consultaPublica(Request $request): JsonResponse
    {
        $documento = $request->query('documento');
        $placa = $request->query('placa');

        if (!$documento || !$placa) {
            return response()->json(['message' => 'Os parâmetros documento e placa são obrigatórios.'], 422);
        }

        $resultado = $this->consultarOSPublica->executar($documento, $placa);

        if (!$resultado) {
            return response()->json(['message' => 'Nenhuma OS encontrada para os dados informados.'], 404);
        }

        return response()->json(['data' => $resultado]);
    }
}
