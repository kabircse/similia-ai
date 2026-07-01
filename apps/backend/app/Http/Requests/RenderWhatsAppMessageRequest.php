<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenderWhatsAppMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['required', 'integer', 'exists:whatsapp_message_templates,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:clinic_appointments,id'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable'],
        ];
    }
}
