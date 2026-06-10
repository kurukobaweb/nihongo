<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubmissionRequest extends FormRequest
{
    private const MAX_AUDIO_KILOBYTES = 10240;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'question_id' => [
                'required',
                'integer',
                Rule::exists('questions', 'id')->where('is_published', true),
            ],
            'audio' => [
                'required',
                'file',
                'mimetypes:audio/webm,video/webm',
                // MVP provisional limit for about 120 seconds of WebM/Opus audio.
                'max:'.self::MAX_AUDIO_KILOBYTES,
            ],
        ];
    }
}
