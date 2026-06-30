<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateFollowUpAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'previous_visit_id' => [
                'nullable',
                'integer',
                'exists:patient_visits,id',
            ],
            'prescription_id' => [
                'nullable',
                'integer',
                'exists:patient_prescriptions,id',
            ],
            'include_timeline_context' => ['nullable', 'boolean'],
            'limit_previous_visits' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
