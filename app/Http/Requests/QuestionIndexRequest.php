<?php

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'difficulty' => ['sometimes', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'question_format' => ['sometimes', 'string', Rule::in(Question::QUESTION_FORMATS)],
            'category' => ['sometimes', 'string', 'max:100'],
            'tag' => ['sometimes', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{difficulty?: string, question_format?: string, category?: string, tag?: string}
     */
    public function filters(): array
    {
        return $this->safe()->only(['difficulty', 'question_format', 'category', 'tag']);
    }
}
