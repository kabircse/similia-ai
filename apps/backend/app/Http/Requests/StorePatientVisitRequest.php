<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'visit_date' => ['required', 'date'],
            'visit_type' => ['required', Rule::in(['initial', 'follow_up'])],
            'status' => ['required', Rule::in(['draft', 'completed'])],
            'case_source' => ['required', Rule::in(['manual', 'raw', 'mixed'])],

            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'raw_case_text' => ['nullable', 'string', 'max:20000'],

            'case_sections' => ['nullable', 'array'],
            'case_sections.location' => ['nullable', 'string', 'max:5000'],
            'case_sections.sensation' => ['nullable', 'string', 'max:5000'],
            'case_sections.modalities' => ['nullable', 'string', 'max:5000'],
            'case_sections.concomitants' => ['nullable', 'string', 'max:5000'],
            'case_sections.mentals' => ['nullable', 'string', 'max:5000'],
            'case_sections.generals' => ['nullable', 'string', 'max:5000'],
            'case_sections.thermal_state' => ['nullable', 'string', 'max:2000'],
            'case_sections.thirst' => ['nullable', 'string', 'max:2000'],
            'case_sections.appetite' => ['nullable', 'string', 'max:2000'],
            'case_sections.food_desires' => ['nullable', 'string', 'max:2000'],
            'case_sections.food_aversions' => ['nullable', 'string', 'max:2000'],
            'case_sections.sleep' => ['nullable', 'string', 'max:3000'],
            'case_sections.dreams' => ['nullable', 'string', 'max:3000'],
            'case_sections.stool' => ['nullable', 'string', 'max:3000'],
            'case_sections.urine' => ['nullable', 'string', 'max:3000'],
            'case_sections.menses' => ['nullable', 'string', 'max:3000'],
            'case_sections.past_history' => ['nullable', 'string', 'max:5000'],
            'case_sections.family_history' => ['nullable', 'string', 'max:5000'],
            'case_sections.current_medicine' => ['nullable', 'string', 'max:5000'],
            'case_sections.reports_note' => ['nullable', 'string', 'max:5000'],

            'doctor_notes' => ['nullable', 'string', 'max:10000'],
            'next_follow_up_date' => ['nullable', 'date'],
        ];
    }
}