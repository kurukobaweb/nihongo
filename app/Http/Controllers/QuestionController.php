<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionIndexRequest;
use App\Models\Question;
use App\Services\QuestionQueryService;
use Illuminate\Http\JsonResponse;

class QuestionController extends Controller
{
    public function index(QuestionIndexRequest $request, QuestionQueryService $questions): JsonResponse
    {
        return response()->json([
            'data' => $questions->publishedQuestions($request->filters())
                ->map(fn (Question $question): array => [
                    'id' => $question->id,
                    'title' => $question->title,
                    'prompt_text' => $question->prompt_text,
                    'difficulty' => $question->difficulty,
                    'question_format' => [
                        'value' => $question->question_format,
                        'label' => Question::QUESTION_FORMAT_LABELS[$question->question_format],
                    ],
                    'recommended_duration_seconds' => $question->recommended_duration_seconds,
                    'has_model_answer' => $question->has_model_answer,
                    'display_order' => $question->display_order,
                    'category' => [
                        'id' => $question->category->id,
                        'name' => $question->category->name,
                        'slug' => $question->category->slug,
                        'description' => $question->category->description,
                    ],
                    'tags' => $question->tags
                        ->sortBy('name')
                        ->values()
                        ->map(fn ($tag): array => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'slug' => $tag->slug,
                        ])
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
