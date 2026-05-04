<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getId(),
            'nome' => $this->resource->getNome(),
            'documento' => $this->resource->getDocumento()->getValue(),
            'celular' => $this->resource->getCelular(),
            'email' => $this->resource->getEmail(),
        ];
    }
}
