<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorReviewQueueItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'in_review', 'completed', 'dismissed'])],
            'doctor_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
