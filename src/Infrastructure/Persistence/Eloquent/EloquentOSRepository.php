<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\OS as OSModel;
use Domain\Atendimento\Entities\OS;
use Domain\Atendimento\Repositories\OSRepository;

class EloquentOSRepository implements OSRepository
{
    public function findById(int $id): ?OS
    {
        $model = OSModel::find($id);

        return $model ? OSMapper::toEntity($model) : null;
    }

    public function findAll(array $filtros = []): array
    {
        $query = OSModel::query();

        if (isset($filtros['cliente_id'])) {
            $query->where('cliente_id', $filtros['cliente_id']);
        }

        if (isset($filtros['status_id'])) {
            $query->where('status_atual_id', $filtros['status_id']);
        }

        if (isset($filtros['veiculo_id'])) {
            $query->where('veiculo_id', $filtros['veiculo_id']);
        }

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
}
