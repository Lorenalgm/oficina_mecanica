<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOSServicoInsumoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'insumo_id' => ['required', 'integer', 'exists:insumos,id'],
            'quantidade' => ['required', 'integer', 'min:1'],
        ];
    }
}
