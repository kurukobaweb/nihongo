<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    protected $fillable = [
        'submission_id',
        'transcript',
        'duration_seconds',
        'characters_per_minute',
        'speed_assessment',
        'overall_score',
        'comment',
        'azure_request_id',
        'pronunciation_result',
        'fluency_result',
        'raw_azure_response',
        'processing_time_ms',
    ];

    protected $casts = [
        'submission_id' => 'string',
        'duration_seconds' => 'decimal:2',
        'characters_per_minute' => 'integer',
        'overall_score' => 'decimal:2',
        'pronunciation_result' => 'array',
        'fluency_result' => 'array',
        'raw_azure_response' => 'array',
        'processing_time_ms' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
