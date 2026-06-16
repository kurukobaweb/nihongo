<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubmissionRequest;
use App\Jobs\ProcessSpeechEvaluationJob;
use App\Models\Evaluation;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request): JsonResponse
    {
        $submissionId = (string) Str::uuid();
        $submittedAt = Carbon::now();
        $audioPath = sprintf(
            'audio/%s/%s/%s.webm',
            $submittedAt->format('Y'),
            $submittedAt->format('m'),
            $submissionId,
        );

        $audio = $request->file('audio');
        Storage::disk('local')->putFileAs(
            dirname($audioPath),
            $audio,
            basename($audioPath),
        );

        $submission = Submission::query()->create([
            'id' => $submissionId,
            'user_id' => $request->user()->id,
            'question_id' => $request->integer('question_id'),
            'audio_path' => $audioPath,
            'audio_size_bytes' => $audio->getSize(),
            'audio_duration_seconds' => null,
            'status' => 'pending',
            'submitted_at' => $submittedAt,
        ]);

        ProcessSpeechEvaluationJob::dispatch($submission->id);

        return response()->json([
            'submission_id' => $submission->id,
            'status' => $submission->status,
            'question_id' => $submission->question_id,
            'submitted_at' => $submission->submitted_at?->toJSON(),
        ], 202);
    }

    public function status(Request $request, Submission $submission): JsonResponse
    {
        if ($submission->user_id !== $request->user()->id) {
            abort(404);
        }

        $submission->load('evaluation');

        $payload = [
            'submission_id' => $submission->id,
            'status' => $submission->status,
            'completed' => $submission->status === 'completed',
            'failed' => $submission->status === 'failed',
            'result_url' => null,
            'error_message' => $submission->status === 'failed' ? $submission->error_message : null,
        ];

        if ($submission->status === 'completed' && $submission->evaluation instanceof Evaluation) {
            $payload['evaluation'] = [
                'transcript' => $submission->evaluation->transcript,
                'duration_seconds' => $this->nullableFloat($submission->evaluation->duration_seconds),
                'characters_per_minute' => $submission->evaluation->characters_per_minute,
                'speed_assessment' => $submission->evaluation->speed_assessment,
                'overall_score' => $this->nullableFloat($submission->evaluation->overall_score),
            ];
        }

        return response()->json($payload);
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
