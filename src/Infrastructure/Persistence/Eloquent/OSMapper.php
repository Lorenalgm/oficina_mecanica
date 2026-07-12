<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\OS as OSModel;
use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Entities\OSOrcamento;
use Domain\Atendimento\Entities\OSServico;
use Domain\Atendimento\Entities\OSServicoInsumo;
use Domain\Atendimento\Entities\OSStatus;
use Domain\Atendimento\Enums\StatusOrcamento;

class OSMapper
{
    public static function toEntity(OSModel $model): OS
    {
        $os = new OS(
            id: $model->id,
            clienteId: (int) $model->cliente_id,
            veiculoId: (int) $model->veiculo_id,
            statusAtualId: (int) $model->status_atual_id,
            descricaoProblema: $model->descricao_problema,
        );

        if ($model->relationLoaded('statusAtual') && $model->statusAtual) {
            $os->setStatusAtualNome($model->statusAtual->nome);
        }

        if ($model->relationLoaded('cliente') && $model->cliente) {
            $os->setClienteNome($model->cliente->nome);
        }

        if ($model->relationLoaded('veiculo') && $model->veiculo) {
            $os->setVeiculoPlaca($model->veiculo->placa);
        }

        $os->setCreatedAt($model->created_at);

        return $os;
    }

    public static function toDetailEntity(OSModel $model): OS
    {
        $os = self::toEntity($model);

        if ($model->relationLoaded('orcamento') && $model->orcamento) {
            $orc = $model->orcamento;
            $os->setOrcamento(new OSOrcamento(
                id: $orc->id,
                osId: (int) $orc->os_id,
                valorTotal: (float) $orc->valor_total,
                dataOrcamento: $orc->data_orcamento,
                dataAprovacao: $orc->data_aprovacao,
                status: StatusOrcamento::from($orc->status),
                approvalToken: $orc->approval_token,
            ));
        }

        if ($model->relationLoaded('servicos')) {
            $servicos = $model->servicos->map(function ($osServico) {
                $sv = new OSServico(
                    id: $osServico->id,
                    osId: (int) $osServico->os_id,
                    servicoId: (int) $osServico->servico_id,
                );
                if ($osServico->relationLoaded('servico') && $osServico->servico) {
                    $sv->setServicoNome($osServico->servico->nome);
                    $sv->setServicoValor((float) $osServico->servico->valor);
                }
                if ($osServico->relationLoaded('insumos')) {
                    $insumos = $osServico->insumos->map(function ($osi) {
                        $insumoEntity = new OSServicoInsumo(
                            id: $osi->id,
                            osServicoId: (int) $osi->os_servico_id,
                            insumoId: (int) $osi->insumo_id,
                            quantidade: (int) $osi->quantidade,
                        );
                        if ($osi->relationLoaded('insumo') && $osi->insumo) {
                            $insumoEntity->setInsumoNome($osi->insumo->nome);
                            $insumoEntity->setInsumoValor((float) $osi->insumo->valor);
                        }
                        return $insumoEntity;
                    })->all();
                    $sv->setInsumos($insumos);
                }
                return $sv;
            })->all();
            $os->setServicos($servicos);
        }

        if ($model->relationLoaded('historicoStatus')) {
            $historico = $model->historicoStatus->map(function ($h) {
                $st = new OSStatus(
                    id: $h->id,
                    osId: (int) $h->os_id,
                    statusId: (int) $h->status_id,
                    dataStatus: $h->data_status,
                );
                if ($h->relationLoaded('status') && $h->status) {
                    $st->setStatusNome($h->status->nome);
                }
                return $st;
            })->all();
            $os->setHistoricoStatus($historico);
        }

        return $os;
    }

    public static function toArray(OS $os): array
    {
        return [
            'cliente_id' => $os->getClienteId(),
            'veiculo_id' => $os->getVeiculoId(),
            'status_atual_id' => $os->getStatusAtualId(),
            'descricao_problema' => $os->getDescricaoProblema(),
        ];
    }
}
