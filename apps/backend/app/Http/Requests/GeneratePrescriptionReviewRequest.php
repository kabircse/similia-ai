<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePrescriptionReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prescription_id' => ['nullable', 'integer', 'exists:patient_prescriptions,id'],
            'include_remedy_suggestion' => ['nullable', 'boolean'],
            'include_potency_guidance' => ['nullable', 'boolean'],
            'include_relationship_guidance' => ['nullable', 'boolean'],
            'include_follow_up_analysis' => ['nullable', 'boolean'],
            'response_language' => ['nullable', 'string', 'in:auto,bn-BD,en-US,hi-IN'],
        ];
    }
}
