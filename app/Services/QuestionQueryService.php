<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class QuestionQueryService
{
    /**
     * @param  array{difficulty?: string, question_format?: string, category?: string, tag?: string}  $filters
     * @return Collection<int, Question>
     */
    public function publishedQuestions(array $filters): Collection
    {
        return Question::query()
            ->select([
                'id',
                'category_id',
                'title',
                'prompt_text',
                'difficulty',
                'question_format',
                'recommended_duration_seconds',
                'has_model_answer',
                'display_order',
            ])
            ->with([
                'category:id,name,slug,description,display_order,is_active',
                'tags:id,name,slug',
            ])
            ->where('is_published', true)
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->when(
                $filters['difficulty'] ?? null,
                fn (Builder $query, string $difficulty) => $query->where('difficulty', $difficulty),
            )
            ->when(
                $filters['question_format'] ?? null,
                fn (Builder $query, string $questionFormat) => $query->where('question_format', $questionFormat),
            )
            ->when(
                $filters['category'] ?? null,
                fn (Builder $query, string $category) => $query->whereHas(
                    'category',
                    fn (Builder $categoryQuery) => $this->applyIdOrSlugFilter($categoryQuery, $category),
                ),
            )
            ->when(
                $filters['tag'] ?? null,
                fn (Builder $query, string $tag) => $query->whereHas(
                    'tags',
                    fn (Builder $tagQuery) => $this->applyIdOrSlugFilter($tagQuery, $tag),
                ),
            )
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    private function applyIdOrSlugFilter(Builder $query, string $value): Builder
    {
        return ctype_digit($value)
            ? $query->whereKey((int) $value)
            : $query->where('slug', $value);
    }
}
