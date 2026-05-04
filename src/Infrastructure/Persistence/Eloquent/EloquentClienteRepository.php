<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Cliente as ClienteModel;
use Domain\Identidade\Entities\Cliente;
use Domain\Identidade\Repositories\ClienteRepository;

class EloquentClienteRepository implements ClienteRepository
{
    public function findById(int $id): ?Cliente
    {
        $model = ClienteModel::find($id);

        return $model ? ClienteMapper::toEntity($model) : null;
    }

    public function findAll(): array
    {
        return ClienteModel::all()
            ->map(fn ($m) => ClienteMapper::toEntity($m))
            ->all();
    }

    public function save(Cliente $cliente): Cliente
    {
        $dados = ClienteMapper::toArray($cliente);

        if ($cliente->getId()) {
            $model = ClienteModel::findOrFail($cliente->getId());
            $model->update($dados);
        } else {
            $model = ClienteModel::create($dados);
        }

        return ClienteMapper::toEntity($model);
    }

    public function delete(int $id): void
    {
        ClienteModel::findOrFail($id)->delete();
    }
}
