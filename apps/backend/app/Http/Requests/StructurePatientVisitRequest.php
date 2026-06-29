<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StructurePatientVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'raw_case_text' => ['nullable', 'string', 'max:20000'],
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'overwrite_existing_sections' => ['sometimes', 'boolean'],
        ];
    }
}