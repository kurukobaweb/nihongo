<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
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

        return response()->json([
            'id' => $submission->id,
            'status' => $submission->status,
            'question_id' => $submission->question_id,
            'audio_path' => $submission->audio_path,
            'submitted_at' => $submission->submitted_at?->toJSON(),
        ], 201);
    }
}
