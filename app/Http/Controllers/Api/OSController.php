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
use Application\OS\UseCases\CalcularTempoMedio;
use Application\OS\UseCases\ConsultarOSPublica;
use Application\OS\UseCases\CriarOS;
use Application\OS\UseCases\DetalharOS;
use Application\OS\UseCases\ListarOS;
use Application\Saga\UseCases\IniciarSagaOS;
use App\Support\CorrelationContext;
use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Saga\SagaJaAtiva;
use Domain\Atendimento\Filters\FiltroListagemOS;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class OSController extends Controller
{
    public function __construct(
        private CriarOS $criarOS,
        private ListarOS $listarOS,
        private DetalharOS $detalharOS,
        private AdicionarServicoNaOS $adicionarServico,
        private AdicionarInsumoNoServico $adicionarInsumo,
        private IniciarSagaOS $iniciarSaga,
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
            servicos: $request->input('servicos', []),
            filial: $request->input('filial', OS::FILIAL_PADRAO),
        );

        // Alimenta o painel "Volume diário de ordens de serviço" no New Relic.
        Log::info('os_created', [
            'event_type' => 'os_created',
            'os_id' => (int) $os->getId(),
            'cliente_id' => (int) $request->cliente_id,
            'veiculo_id' => (int) $request->veiculo_id,
        ]);

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
            return $this->falhaNoProcessamento($e, $id);
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
            return $this->falhaNoProcessamento($e, $id);
        }
    }

    public function solicitarOrcamento(int $id): JsonResponse
    {
        try {
            $saga = $this->iniciarSaga->executar($id, CorrelationContext::getOrGenerate());
        } catch (SagaJaAtiva $e) {
            return response()->json(['message' => $e->getMessage(), 'saga_id' => $e->saga->id], 409);
        } catch (\RuntimeException $e) {
            return $this->falhaNoProcessamento($e, $id);
        }

        return response()->json(['data' => [
            'saga_id' => $saga->id,
            'os_id' => $saga->osId,
            'estado' => $saga->estado()->value,
            'acompanhar' => "/api/os/{$id}/saga",
        ]], 202);
    }

    private function falhaNoProcessamento(\RuntimeException $e, int $osId): JsonResponse
    {
        Log::error($e->getMessage(), [
            'event_type' => 'os_processing_failure',
            'os_id' => $osId,
            'exception_class' => $e::class,
        ]);

        return response()->json(['message' => $e->getMessage()], 422);
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
        } catch (SagaJaAtiva $e) {
            return response()->json(['message' => $e->getMessage(), 'saga_id' => $e->saga->id], 409);
        } catch (\RuntimeException $e) {
            return $this->falhaNoProcessamento($e, $id);
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
