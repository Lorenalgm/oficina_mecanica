<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'celular' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
