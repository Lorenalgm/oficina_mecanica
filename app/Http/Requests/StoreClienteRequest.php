<?php

namespace App\Http\Requests;

use Domain\Shared\ValueObjects\Documento;
use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->documento) {
            $this->merge(['documento' => preg_replace('/\D/', '', $this->documento)]);
        }
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'documento' => ['required', 'string', 'unique:clientes,documento'],
            'celular' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (!$v->errors()->has('documento') && $this->documento) {
                try {
                    new Documento($this->documento);
                } catch (\InvalidArgumentException $e) {
                    $v->errors()->add('documento', $e->getMessage());
                }
            }
        });
    }
}
