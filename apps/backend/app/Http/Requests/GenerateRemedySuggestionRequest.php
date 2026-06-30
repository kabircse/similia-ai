<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateRemedySuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'repertorization_run_id' => ['nullable', 'integer', 'exists:repertorization_runs,id'],
            'method' => ['nullable', 'string', Rule::in(['weighted', 'cross', 'eliminative'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5'],
            'include_potency' => ['nullable', 'boolean'],
            'include_relationship' => ['nullable', 'boolean'],
            'include_medical_safety' => ['nullable', 'boolean'],
            'include_organon' => ['nullable', 'boolean'],
        ];
    }
}
