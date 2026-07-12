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
use Domain\Atendimento\Exceptions\TokenAprovacaoInvalido;
use Domain\Atendimento\Filters\FiltroListagemOS;
use Domain\Catalogo\Exceptions\EstoqueInsuficienteException;
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
        $filtro = FiltroListagemOS::fromArray(array_filter([
            'cliente_id' => $request->query('cliente_id'),
            'status_id' => $request->query('status_id'),
            'veiculo_id' => $request->query('veiculo_id'),
        ]));

        $entities = $this->listarOS->executar($filtro);

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
                'id' => $osServico->getId(),
                'os_id' => $osServico->getOsId(),
                'servico_id' => $osServico->getServicoId(),
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
                'id' => $item->getId(),
                'os_servico_id' => $item->getOsServicoId(),
                'insumo_id' => $item->getInsumoId(),
                'quantidade' => $item->getQuantidade(),
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
                'id' => $orcamento->getId(),
                'os_id' => $orcamento->getOsId(),
                'valor_total' => $orcamento->getValorTotal(),
                'status' => $orcamento->getStatus()->value,
                'data_orcamento' => $orcamento->getDataOrcamento(),
                'approval_token' => $orcamento->getApprovalToken(),
            ]], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function aprovarOrcamento(Request $request, int $id): JsonResponse
    {
        try {
            $orcamento = $this->aprovarOrcamento->executar($id, $this->extrairToken($request));

            return response()->json(['data' => [
                'id' => $orcamento->getId(),
                'status' => $orcamento->getStatus()->value,
                'data_aprovacao' => $orcamento->getDataAprovacao(),
            ]]);
        } catch (TokenAprovacaoInvalido $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (EstoqueInsuficienteException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recusarOrcamento(Request $request, int $id): JsonResponse
    {
        try {
            $orcamento = $this->recusarOrcamento->executar($id, $this->extrairToken($request));

            return response()->json(['data' => [
                'id' => $orcamento->getId(),
                'status' => $orcamento->getStatus()->value,
            ]]);
        } catch (TokenAprovacaoInvalido $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function extrairToken(Request $request): ?string
    {
        $token = $request->input('token', $request->query('token'));

        return is_string($token) && $token !== '' ? $token : null;
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
