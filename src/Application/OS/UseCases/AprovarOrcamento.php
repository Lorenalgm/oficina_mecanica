<?php

namespace Application\OS\UseCases;

use App\Models\Insumo as InsumoModel;
use App\Models\OS as OSModel;
use App\Models\OSOrcamento as OSOrcamentoModel;
use App\Models\OSStatus as OSStatusModel;
use App\Models\Status;
use Domain\Catalogo\Exceptions\EstoqueInsuficienteException;
use Infrastructure\Persistence\Eloquent\InsumoMapper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AprovarOrcamento
{
    public function executar(int $osId): OSOrcamentoModel
    {
        $orcamento = OSOrcamentoModel::where('os_id', $osId)->firstOrFail();

        if ($orcamento->status !== 'pendente') {
            throw new RuntimeException('Somente orçamentos pendentes podem ser aprovados.');
        }

        $os = OSModel::with(['servicos.insumos.insumo'])->findOrFail($osId);

        DB::transaction(function () use ($orcamento, $os) {
            foreach ($os->servicos as $osServico) {
                foreach ($osServico->insumos as $osServicoInsumo) {
                    $model = InsumoModel::findOrFail($osServicoInsumo->insumo_id);
                    $entidade = InsumoMapper::toEntity($model);
                    $entidade->darBaixa($osServicoInsumo->quantidade);
                    $model->quantidade_estoque = $entidade->getQuantidadeEstoque();
                    $model->save();
                }
            }

            $orcamento->status = 'aprovado';
            $orcamento->data_aprovacao = now();
            $orcamento->save();

            $statusEmExecucao = Status::where('nome', 'Em execução')->first();
            if ($statusEmExecucao) {
                $os->status_atual_id = $statusEmExecucao->id;
                $os->save();
                OSStatusModel::create([
                    'os_id' => $os->id,
                    'status_id' => $statusEmExecucao->id,
                    'data_status' => now(),
                ]);
            }
        });

        return $orcamento->fresh();
    }
}
