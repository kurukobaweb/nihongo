<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Submission extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'question_id',
        'audio_path',
        'audio_size_bytes',
        'audio_duration_seconds',
        'status',
        'error_message',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'integer',
        'question_id' => 'integer',
        'audio_size_bytes' => 'integer',
        'audio_duration_seconds' => 'decimal:2',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class);
    }
}
