<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompareMateriaMedicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'repertorization_run_id' => ['nullable', 'integer', 'exists:repertorization_runs,id'],
            'method' => ['nullable', 'string', 'in:weighted,cross,eliminative'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
