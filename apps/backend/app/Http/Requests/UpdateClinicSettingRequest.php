<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'clinic_name' => ['required', 'string', 'max:180'],
            'tagline' => ['nullable', 'string', 'max:220'],

            'doctor_display_name' => ['nullable', 'string', 'max:160'],
            'doctor_qualification' => ['nullable', 'string', 'max:160'],

            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:180'],
            'website' => ['nullable', 'string', 'max:180'],

            'address' => ['nullable', 'string', 'max:2000'],
            'logo_url' => ['nullable', 'string', 'max:500'],

            'default_currency' => ['nullable', 'string', 'max:10'],
            'default_consultation_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'default_followup_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'medicine_fee_included' => ['nullable', 'boolean'],

            'prescription_footer' => ['nullable', 'string', 'max:3000'],
            'case_sheet_footer' => ['nullable', 'string', 'max:3000'],
            'prescription_header' => ['nullable', 'string', 'max:3000'],
            'prescription_disclaimer' => ['nullable', 'string', 'max:3000'],
            'appointment_default_duration_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'appointment_default_timezone' => ['nullable', 'string', 'max:80'],
        ];
    }
}
