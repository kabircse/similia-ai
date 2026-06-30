<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnswerCaseQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'question_message_id' => [
                'required',
                'integer',
                'exists:case_question_messages,id',
            ],
            'answer_text' => ['required', 'string', 'max:20000'],
            'merge_to_case_text' => ['nullable', 'boolean'],
            'apply_to_case_sections' => ['nullable', 'boolean'],
        ];
    }
}
