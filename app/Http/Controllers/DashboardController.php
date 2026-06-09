<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $questionId = $request->query('question_id');
        $selectedQuestion = null;
        $selectedQuestionUnavailable = false;

        if ($questionId !== null) {
            if (is_string($questionId) && ctype_digit($questionId)) {
                $question = Question::query()
                    ->select([
                        'id',
                        'category_id',
                        'title',
                        'prompt_text',
                        'difficulty',
                        'question_format',
                        'recommended_duration_seconds',
                        'has_model_answer',
                    ])
                    ->with([
                        'category:id,name,slug',
                        'tags:id,name,slug',
                    ])
                    ->whereKey((int) $questionId)
                    ->where('is_published', true)
                    ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
                    ->first();

                if ($question !== null) {
                    $selectedQuestion = [
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
                        'category' => [
                            'id' => $question->category->id,
                            'name' => $question->category->name,
                            'slug' => $question->category->slug,
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
                    ];
                }
            }

            $selectedQuestionUnavailable = $selectedQuestion === null;
        }

        return Inertia::render('Dashboard', [
            'selectedQuestion' => $selectedQuestion,
            'selectedQuestionUnavailable' => $selectedQuestionUnavailable,
        ]);
    }
}
