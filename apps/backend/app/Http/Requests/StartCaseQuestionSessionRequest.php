<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartCaseQuestionSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'language' => ['nullable', 'string', 'max:20'],
            'response_language' => ['nullable', 'string', 'in:auto,bn-BD,en-US,hi-IN'],
            'mode' => ['nullable', Rule::in([
                'ai_missing_questions',
                'from_existing_missing_questions',
            ])],
            'max_questions' => ['nullable', 'integer', 'min:1', 'max:20'],
            'replace_active_session' => ['nullable', 'boolean'],
        ];
    }
}
