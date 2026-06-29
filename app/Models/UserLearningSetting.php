<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLearningSetting extends Model
{
    public const QUESTION_FORMAT_SINGLE_PROMPT = 'single_prompt';
    public const QUESTION_FORMAT_TWO_CHOICE = 'two_choice';

    public const TIMER_DISPLAY_COUNT_DOWN = 'count_down';
    public const TIMER_DISPLAY_COUNT_UP = 'count_up';
    public const TIMER_DISPLAY_HIDDEN = 'hidden';

    public const DEFAULTS = [
        'question_format_preference' => self::QUESTION_FORMAT_SINGLE_PROMPT,
        'speech_duration_seconds' => 60,
        'timer_display_mode' => self::TIMER_DISPLAY_COUNT_DOWN,
        'force_stop_enabled' => true,
        'transcript_display_enabled' => true,
    ];

    public const QUESTION_FORMAT_OPTIONS = [
        self::QUESTION_FORMAT_SINGLE_PROMPT,
        self::QUESTION_FORMAT_TWO_CHOICE,
    ];

    public const TIMER_DISPLAY_OPTIONS = [
        self::TIMER_DISPLAY_COUNT_DOWN,
        self::TIMER_DISPLAY_COUNT_UP,
        self::TIMER_DISPLAY_HIDDEN,
    ];

    protected $table = 'user_learning_settings';

    protected $fillable = [
        'user_id',
        'question_format_preference',
        'speech_duration_seconds',
        'timer_display_mode',
        'force_stop_enabled',
        'transcript_display_enabled',
    ];

    protected $casts = [
        'speech_duration_seconds' => 'integer',
        'force_stop_enabled' => 'boolean',
        'transcript_display_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSettingsArray(): array
    {
        return [
            'question_format_preference' => $this->question_format_preference,
            'speech_duration_seconds' => $this->speech_duration_seconds,
            'timer_display_mode' => $this->timer_display_mode,
            'force_stop_enabled' => $this->force_stop_enabled,
            'transcript_display_enabled' => $this->transcript_display_enabled,
        ];
    }
}
