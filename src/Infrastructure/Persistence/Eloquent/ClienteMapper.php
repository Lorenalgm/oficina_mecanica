<?php

namespace Infrastructure\Persistence\Eloquent;

use App\Models\Cliente as ClienteModel;
use Domain\Identidade\Entities\Cliente;
use Domain\Shared\ValueObjects\Documento;

class ClienteMapper
{
    public static function toEntity(ClienteModel $model): Cliente
    {
        return new Cliente(
            id: $model->id,
            nome: $model->nome,
            documento: new Documento($model->documento),
            celular: $model->celular,
            email: $model->email,
        );
    }

    public static function toArray(Cliente $cliente): array
    {
        return [
            'nome' => $cliente->getNome(),
            'documento' => $cliente->getDocumento()->getValue(),
            'celular' => $cliente->getCelular(),
            'email' => $cliente->getEmail(),
        ];
    }
}
