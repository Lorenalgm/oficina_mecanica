<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Servico as ServicoModel;
use Domain\Catalogo\Entities\Servico;
use Domain\Catalogo\Repositories\ServicoRepository;

class EloquentServicoRepository implements ServicoRepository
{
    public function findById(int $id): ?Servico
    {
        $model = ServicoModel::find($id);

        return $model ? ServicoMapper::toEntity($model) : null;
    }

    public function findAll(): array
    {
        return ServicoModel::all()
            ->map(fn ($m) => ServicoMapper::toEntity($m))
            ->all();
    }

    public function save(Servico $servico): Servico
    {
        $dados = ServicoMapper::toArray($servico);

        if ($servico->getId()) {
            $model = ServicoModel::findOrFail($servico->getId());
            $model->update($dados);
        } else {
            $model = ServicoModel::create($dados);
        }

        return ServicoMapper::toEntity($model);
    }

    public function delete(int $id): void
    {
        ServicoModel::findOrFail($id)->delete();
    }
}
