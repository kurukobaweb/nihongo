<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Submission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    public function __invoke(Request $request, Submission $submission): Response
    {
        if ($submission->user_id !== $request->user()->id) {
            abort(404);
        }

        $submission->load([
            'evaluation',
            'question.category',
            'question.tags',
        ]);

        if ($submission->status !== 'completed' || ! $submission->evaluation instanceof Evaluation) {
            abort(404);
        }

        $question = $submission->question;
        $evaluation = $submission->evaluation;
        $questionTags = $question?->tags ?? collect();

        return Inertia::render('Submissions/Result', [
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status,
                'submitted_at' => $submission->submitted_at?->toJSON(),
                'completed_at' => $submission->completed_at?->toJSON(),
            ],
            'question' => [
                'id' => $question?->id,
                'title' => $question?->title,
                'prompt_text' => $question?->prompt_text,
                'difficulty' => $question?->difficulty,
                'question_format' => [
                    'value' => $question?->question_format,
                    'label' => $question !== null && isset($question::QUESTION_FORMAT_LABELS[$question->question_format])
                        ? $question::QUESTION_FORMAT_LABELS[$question->question_format]
                        : $question?->question_format,
                ],
                'recommended_duration_seconds' => $question?->recommended_duration_seconds,
                'category' => $question?->category === null ? null : [
                    'id' => $question->category->id,
                    'name' => $question->category->name,
                    'slug' => $question->category->slug,
                ],
                'tags' => $questionTags
                    ->sortBy('name')
                    ->values()
                    ->map(fn ($tag): array => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ])
                    ->all() ?? [],
            ],
            'evaluation' => [
                'transcript' => $evaluation->transcript,
                'duration_seconds' => $this->nullableFloat($evaluation->duration_seconds),
                'characters_per_minute' => $evaluation->characters_per_minute,
                'speed_assessment' => $evaluation->speed_assessment,
                'overall_score' => $this->nullableFloat($evaluation->overall_score),
                'comment' => $evaluation->comment,
            ],
        ]);
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
