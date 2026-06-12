<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    public const QUESTION_FORMAT_SINGLE_PROMPT = 'single_prompt';

    public const QUESTION_FORMAT_TWO_CHOICE = 'two_choice';

    public const QUESTION_FORMATS = [
        self::QUESTION_FORMAT_SINGLE_PROMPT,
        self::QUESTION_FORMAT_TWO_CHOICE,
    ];

    public const QUESTION_FORMAT_LABELS = [
        self::QUESTION_FORMAT_SINGLE_PROMPT => '単体問題',
        self::QUESTION_FORMAT_TWO_CHOICE => '二者択一',
    ];

    protected $fillable = [
        'category_id',
        'title',
        'prompt_text',
        'difficulty',
        'question_format',
        'recommended_duration_seconds',
        'has_model_answer',
        'model_answer_text',
        'is_published',
        'display_order',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'recommended_duration_seconds' => 'integer',
        'has_model_answer' => 'boolean',
        'is_published' => 'boolean',
        'display_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_tag');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
