<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClinicalDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],

            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],

            'period' => [
                'nullable',
                Rule::in(['7d', '30d', '90d', 'this_month', 'last_month', 'this_year', 'custom']),
            ],
        ];
    }
}
