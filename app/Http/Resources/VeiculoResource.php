<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VeiculoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getId(),
            'placa' => $this->resource->getPlaca()->getValue(),
            'marca' => $this->resource->getMarca(),
            'modelo' => $this->resource->getModelo(),
            'ano' => $this->resource->getAno(),
            'cliente_id' => $this->resource->getClienteId(),
        ];
    }
}
