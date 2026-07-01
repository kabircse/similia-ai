<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVoiceTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'language' => ['required', 'string', 'max:20'],
            'transcript_text' => ['required', 'string', 'max:50000'],
            'segments' => ['nullable', 'array'],

            'merge_to_case_text' => ['nullable', 'boolean'],
            'merge_mode' => ['nullable', Rule::in(['append', 'prepend', 'replace'])],

            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
