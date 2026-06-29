<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRubricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'repertory_rubric_id' => ['required', 'integer', 'exists:repertory_rubrics,id'],
            'symptom_type' => [
                'required',
                Rule::in([
                    'mental',
                    'general',
                    'particular',
                    'modality',
                    'concomitant',
                    'pathological',
                    'common',
                    'other',
                ]),
            ],
            'importance' => [
                'required',
                Rule::in(['essential', 'important', 'supportive', 'optional']),
            ],
            'weight' => ['required', 'integer', 'min:1', 'max:5'],
            'is_essential' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:3000'],
        ];
    }
}