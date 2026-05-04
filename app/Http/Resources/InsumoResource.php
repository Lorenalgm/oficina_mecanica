<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsumoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getId(),
            'nome' => $this->resource->getNome(),
            'valor' => $this->resource->getValor(),
            'quantidade_estoque' => $this->resource->getQuantidadeEstoque(),
        ];
    }
}
