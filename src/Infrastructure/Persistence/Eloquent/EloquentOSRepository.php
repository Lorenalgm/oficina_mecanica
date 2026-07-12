<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Insumo as InsumoModel;
use App\Models\OS as OSModel;
use App\Models\OSOrcamento as OSOrcamentoModel;
use App\Models\OSServico as OSServicoModel;
use App\Models\OSServicoInsumo as OSServicoInsumoModel;
use App\Models\OSStatus as OSStatusModel;
use App\Models\Servico as ServicoModel;
use App\Models\Status as StatusModel;
use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Entities\OSOrcamento;
use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Enums\PrioridadeStatus;
use Domain\Atendimento\Enums\StatusOrcamento;
use Domain\Atendimento\Filters\FiltroListagemOS;
use Domain\Atendimento\Repositories\OSRepository;
use Illuminate\Support\Facades\DB;

class EloquentOSRepository implements OSRepository
{
    private const RELACOES_DETALHE = [
        'cliente',
        'veiculo',
        'statusAtual',
        'servicos.servico',
        'servicos.insumos.insumo',
        'historicoStatus.status',
        'orcamento',
    ];

    public function findById(int $id): ?OS
    {
        $model = OSModel::with(self::RELACOES_DETALHE)->find($id);

        return $model ? OSMapper::toDetailEntity($model) : null;
    }

    public function findAll(FiltroListagemOS $filtro): array
    {
        $query = OSModel::query()
            ->with(['statusAtual', 'cliente', 'veiculo'])
            ->join('status', 'os.status_atual_id', '=', 'status.id')
            ->whereNotIn('status.nome', PrioridadeStatus::EXCLUIDOS_DA_LISTAGEM)
            ->select('os.*');

        if ($filtro->clienteId !== null) {
            $query->where('os.cliente_id', $filtro->clienteId);
        }

        if ($filtro->statusId !== null) {
            $query->where('os.status_atual_id', $filtro->statusId);
        }

        if ($filtro->veiculoId !== null) {
            $query->where('os.veiculo_id', $filtro->veiculoId);
        }

        [$caseSql, $bindings] = $this->ordenacaoPorPrioridade();

        $query->orderByRaw($caseSql, $bindings)
            ->orderBy('os.created_at', 'asc')
            ->orderBy('os.id', 'asc');

        return $query->get()->map(fn ($m) => OSMapper::toEntity($m))->all();
    }

    public function save(OS $os): OS
    {
        $dados = OSMapper::toArray($os);

        if ($os->getId()) {
            $model = OSModel::findOrFail($os->getId());
            $model->update($dados);
        } else {
            $model = OSModel::create($dados);
        }

        return OSMapper::toEntity($model);
    }

    public function osExiste(int $osId): bool
    {
        return OSModel::whereKey($osId)->exists();
    }

    public function findPublica(string $documento, string $placa): ?OS
    {
        $documentoNormalizado = preg_replace('/\D/', '', $documento);
        $placaNormalizada = strtoupper(trim($placa));

        $model = OSModel::with(['statusAtual', 'cliente', 'veiculo'])
            ->whereHas('cliente', fn ($q) => $q->where('documento', $documentoNormalizado))
            ->whereHas('veiculo', fn ($q) => $q->where('placa', $placaNormalizada))
            ->latest()
            ->first();

        return $model ? OSMapper::toEntity($model) : null;
    }

    public function findByApprovalToken(string $token): ?OS
    {
        if ($token === '') {
            return null;
        }

        $model = OSModel::with(self::RELACOES_DETALHE)
            ->whereHas('orcamento', fn ($q) => $q->where('approval_token', $token))
            ->first();

        return $model ? OSMapper::toDetailEntity($model) : null;
    }

    public function findStatusIdByNome(string $nome): ?int
    {
        $status = StatusModel::where('nome', $nome)->first();

        return $status?->id;
    }

    public function statusExiste(int $statusId): bool
    {
        return StatusModel::whereKey($statusId)->exists();
    }

    public function registrarStatus(int $osId, int $statusId): void
    {
        DB::transaction(function () use ($osId, $statusId) {
            $model = OSModel::findOrFail($osId);
            $model->status_atual_id = $statusId;
            $model->save();

            OSStatusModel::create([
                'os_id' => $osId,
                'status_id' => $statusId,
                'data_status' => now(),
            ]);
        });
    }

    public function servicoExiste(int $servicoId): bool
    {
        return ServicoModel::whereKey($servicoId)->exists();
    }

    public function adicionarServico(int $osId, int $servicoId): OSServico
    {
        $model = OSServicoModel::create([
            'os_id' => $osId,
            'servico_id' => $servicoId,
        ]);

        return new OSServico(
            id: $model->id,
            osId: (int) $model->os_id,
            servicoId: (int) $model->servico_id,
        );
    }

    public function osServicoExiste(int $osServicoId): bool
    {
        return OSServicoModel::whereKey($osServicoId)->exists();
    }

    public function insumoExiste(int $insumoId): bool
    {
        return InsumoModel::whereKey($insumoId)->exists();
    }

    public function adicionarInsumo(int $osServicoId, int $insumoId, int $quantidade): OSServicoInsumo
    {
        $model = OSServicoInsumoModel::create([
            'os_servico_id' => $osServicoId,
            'insumo_id' => $insumoId,
            'quantidade' => $quantidade,
        ]);

        return new OSServicoInsumo(
            id: $model->id,
            osServicoId: (int) $model->os_servico_id,
            insumoId: (int) $model->insumo_id,
            quantidade: (int) $model->quantidade,
        );
    }

    public function criarOrcamento(int $osId, float $valorTotal): OSOrcamento
    {
        $model = OSOrcamentoModel::create([
            'os_id' => $osId,
            'valor_total' => $valorTotal,
            'data_orcamento' => now(),
            'status' => StatusOrcamento::Pendente->value,
            'approval_token' => bin2hex(random_bytes(20)),
        ]);

        return $this->toOrcamentoEntity($model);
    }

    public function salvarOrcamento(OSOrcamento $orcamento): void
    {
        $model = OSOrcamentoModel::findOrFail($orcamento->getId());
        $model->status = $orcamento->getStatus()->value;
        $model->data_aprovacao = $orcamento->getDataAprovacao();
        $model->approval_token = $orcamento->getApprovalToken();
        $model->save();
    }

    public function calcularTempoMedioMinutos(): ?float
    {
        $execId = $this->findStatusIdByNome('Em execução');
        $finId = $this->findStatusIdByNome('Finalizada');

        if (!$execId || !$finId) {
            return null;
        }

        $execucoes = OSStatusModel::where('status_id', $execId)->get()->groupBy('os_id');
        $finalizacoes = OSStatusModel::where('status_id', $finId)->get()->groupBy('os_id');

        $diferencas = [];
        foreach ($finalizacoes as $osId => $rowsFin) {
            if (!isset($execucoes[$osId])) {
                continue;
            }
            foreach ($execucoes[$osId] as $exec) {
                foreach ($rowsFin as $fin) {
                    $diferencas[] = ($fin->data_status->getTimestamp() - $exec->data_status->getTimestamp()) / 60;
                }
            }
        }

        if (empty($diferencas)) {
            return null;
        }

        return array_sum($diferencas) / count($diferencas);
    }

    /**
     * Monta o "ORDER BY CASE status.nome WHEN ... THEN <peso> ... ELSE 99 END".
     *
     * @return array{0: string, 1: array<int, string|int>}
     */
    private function ordenacaoPorPrioridade(): array
    {
        $sql = 'CASE status.nome';
        $bindings = [];

        foreach (PrioridadeStatus::mapaDePeso() as $nome => $peso) {
            $sql .= ' WHEN ? THEN ?';
            $bindings[] = $nome;
            $bindings[] = $peso;
        }

        $sql .= ' ELSE 99 END';

        return [$sql, $bindings];
    }

    private function toOrcamentoEntity(OSOrcamentoModel $model): OSOrcamento
    {
        return new OSOrcamento(
            id: $model->id,
            osId: (int) $model->os_id,
            valorTotal: (float) $model->valor_total,
            dataOrcamento: $model->data_orcamento,
            dataAprovacao: $model->data_aprovacao,
            status: StatusOrcamento::from($model->status),
            approvalToken: $model->approval_token,
        );
    }
}
