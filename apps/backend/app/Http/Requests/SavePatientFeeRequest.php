<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePatientFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'max:10'],

            'consultation_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'medicine_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],

            'payment_method' => [
                'nullable',
                'string',
                Rule::in(['cash', 'bkash', 'nagad', 'card', 'bank', 'other']),
            ],

            'payment_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
