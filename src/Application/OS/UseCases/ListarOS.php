<?php

namespace Application\OS\UseCases;

use App\Models\OS as OSModel;
use Illuminate\Database\Eloquent\Collection;

class ListarOS
{
    public function executar(array $filtros = []): Collection
    {
        $query = OSModel::with(['statusAtual', 'cliente', 'veiculo']);

        if (isset($filtros['cliente_id'])) {
            $query->where('cliente_id', $filtros['cliente_id']);
        }

        if (isset($filtros['status_id'])) {
            $query->where('status_atual_id', $filtros['status_id']);
        }

        if (isset($filtros['veiculo_id'])) {
            $query->where('veiculo_id', $filtros['veiculo_id']);
        }

        return $query->orderBy('created_at', 'asc')->get();
    }
}
