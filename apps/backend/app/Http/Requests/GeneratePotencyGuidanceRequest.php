<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePotencyGuidanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prescription_id' => ['nullable', 'integer', 'exists:patient_prescriptions,id'],
            'remedy_id' => ['nullable', 'integer', 'exists:remedies,id'],
            'remedy_name' => ['nullable', 'string', 'max:255'],
            'remedy_code' => ['nullable', 'string', 'max:80'],
            'case_phase' => [
                'nullable',
                Rule::in(['acute', 'chronic', 'follow_up', 'constitutional', 'unclear']),
            ],
            'patient_sensitivity' => [
                'nullable',
                Rule::in(['low', 'moderate', 'high', 'unclear']),
            ],
            'vitality_level' => [
                'nullable',
                Rule::in(['low', 'moderate', 'high', 'unclear']),
            ],
            'pathology_depth' => [
                'nullable',
                Rule::in(['functional', 'structural', 'advanced_pathology', 'unclear']),
            ],
            'include_organon' => ['nullable', 'boolean'],
            'include_philosophy' => ['nullable', 'boolean'],
            'include_follow_up_context' => ['nullable', 'boolean'],
        ];
    }
}
