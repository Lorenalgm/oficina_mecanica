<?php

namespace App\Http\Requests;

use Domain\Shared\ValueObjects\Placa;
use Illuminate\Foundation\Http\FormRequest;

class StoreVeiculoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'placa' => ['required', 'string', 'unique:veiculos,placa'],
            'marca' => ['required', 'string', 'max:100'],
            'modelo' => ['required', 'string', 'max:100'],
            'ano' => ['required', 'integer', 'min:1900', 'max:2100'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (!$v->errors()->has('placa') && $this->placa) {
                try {
                    new Placa($this->placa);
                } catch (\InvalidArgumentException $e) {
                    $v->errors()->add('placa', $e->getMessage());
                }
            }
        });
    }
}
