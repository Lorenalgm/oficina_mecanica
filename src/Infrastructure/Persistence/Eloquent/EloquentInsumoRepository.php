<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Insumo as InsumoModel;
use Domain\Catalogo\Entities\Insumo;
use Domain\Catalogo\Repositories\InsumoRepository;

class EloquentInsumoRepository implements InsumoRepository
{
    public function findById(int $id): ?Insumo
    {
        $model = InsumoModel::find($id);

        return $model ? InsumoMapper::toEntity($model) : null;
    }

    public function findAll(): array
    {
        return InsumoModel::all()
            ->map(fn ($m) => InsumoMapper::toEntity($m))
            ->all();
    }

    public function save(Insumo $insumo): Insumo
    {
        $dados = InsumoMapper::toArray($insumo);

        if ($insumo->getId()) {
            $model = InsumoModel::findOrFail($insumo->getId());
            $model->update($dados);
        } else {
            $model = InsumoModel::create($dados);
        }

        return InsumoMapper::toEntity($model);
    }

    public function delete(int $id): void
    {
        InsumoModel::findOrFail($id)->delete();
    }
}
