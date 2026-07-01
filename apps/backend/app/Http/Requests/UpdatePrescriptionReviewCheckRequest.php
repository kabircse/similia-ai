<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrescriptionReviewCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'doctor_confirmed',
                    'doctor_overridden',
                    'pending',
                ]),
            ],
            'doctor_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
