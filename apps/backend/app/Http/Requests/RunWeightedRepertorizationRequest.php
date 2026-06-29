<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunWeightedRepertorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'settings' => ['nullable', 'array'],
            'settings.limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'settings.strict_essential' => ['nullable', 'boolean'],
        ];
    }
}
