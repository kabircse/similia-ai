<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePatientPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'repertorization_result_id' => [
                'nullable',
                'integer',
                'exists:repertorization_results,id',
            ],

            'source_method' => [
                'nullable',
                'string',
                Rule::in(['manual', 'weighted', 'cross', 'eliminative']),
            ],

            'remedy_code' => ['nullable', 'string', 'max:40'],
            'remedy_name' => ['required', 'string', 'max:160'],
            'potency' => ['required', 'string', 'max:40'],
            'repetition' => ['nullable', 'string', 'max:255'],

            'dose_instruction' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'advice' => ['nullable', 'string', 'max:5000'],
            'food_lifestyle_note' => ['nullable', 'string', 'max:5000'],

            'follow_up_date' => ['nullable', 'date'],

            'status' => ['required', Rule::in(['draft', 'final'])],
        ];
    }
}
