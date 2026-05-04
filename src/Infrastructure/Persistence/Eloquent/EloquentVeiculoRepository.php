<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Veiculo as VeiculoModel;
use Domain\Identidade\Entities\Veiculo;
use Domain\Identidade\Repositories\VeiculoRepository;

class EloquentVeiculoRepository implements VeiculoRepository
{
    public function findById(int $id): ?Veiculo
    {
        $model = VeiculoModel::find($id);

        return $model ? VeiculoMapper::toEntity($model) : null;
    }

    public function findAll(): array
    {
        return VeiculoModel::all()
            ->map(fn ($m) => VeiculoMapper::toEntity($m))
            ->all();
    }

    public function save(Veiculo $veiculo): Veiculo
    {
        $dados = VeiculoMapper::toArray($veiculo);

        if ($veiculo->getId()) {
            $model = VeiculoModel::findOrFail($veiculo->getId());
            $model->update($dados);
        } else {
            $model = VeiculoModel::create($dados);
        }

        return VeiculoMapper::toEntity($model);
    }

    public function delete(int $id): void
    {
        VeiculoModel::findOrFail($id)->delete();
    }
}
