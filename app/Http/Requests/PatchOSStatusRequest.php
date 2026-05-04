<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatchOSStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status_id' => ['required', 'integer', 'exists:status,id'],
        ];
    }
}
