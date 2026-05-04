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
        ];
    }
}
