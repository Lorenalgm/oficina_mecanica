<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOSRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'veiculo_id' => ['required', 'integer', 'exists:veiculos,id'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'descricao_problema' => ['required', 'string'],

            // Serviços e peças (insumos) são opcionais na abertura da OS.
            'servicos' => ['sometimes', 'array'],
            'servicos.*.servico_id' => ['required_with:servicos', 'integer', 'exists:servicos,id'],
            'servicos.*.insumos' => ['sometimes', 'array'],
            'servicos.*.insumos.*.insumo_id' => ['required_with:servicos.*.insumos', 'integer', 'exists:insumos,id'],
            'servicos.*.insumos.*.quantidade' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
