<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:130'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other', 'unknown'])],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'marital_status' => ['nullable', 'string', Rule::in(['single', 'married', 'widowed', 'divorced', 'unknown'])],
            'emergency_contact' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}