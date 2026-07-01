<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateClinicReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'report_type' => [
                'nullable',
                Rule::in(['monthly', 'custom_period', 'yearly']),
            ],

            'period' => [
                'nullable',
                Rule::in(['this_month', 'last_month', 'this_year', 'custom']),
            ],

            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],

            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],

            'response_language' => ['nullable', 'string', 'max:20'],

            'include_finance' => ['nullable', 'boolean'],
            'include_safety' => ['nullable', 'boolean'],
            'include_follow_ups' => ['nullable', 'boolean'],
            'include_recommendations' => ['nullable', 'boolean'],
        ];
    }
}
