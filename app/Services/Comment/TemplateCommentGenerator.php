<?php

namespace App\Services\Comment;

use App\Contracts\CommentGeneratorInterface;
use App\Dto\CommentResult;
use App\Dto\EvaluationResult;

final class TemplateCommentGenerator implements CommentGeneratorInterface
{
    private const FALLBACK_COMMENT = '評価に必要な情報が一部不足しているため、今回は全体的なコメントのみ表示します。録音内容を確認し、もう一度提出するとより詳しい評価ができます。';

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('comment_templates', []);
    }

    public function generate(EvaluationResult $evaluationResult): CommentResult
    {
        $speedCategory = $this->resolveSpeedCategory($evaluationResult->charactersPerMinute);
        $durationCategory = $this->resolveDurationCategory($evaluationResult->durationSeconds);

        if ($speedCategory === null || $durationCategory === null) {
            return $this->fallbackResult($speedCategory, $durationCategory);
        }

        $template = $this->resolveTemplate($speedCategory, $durationCategory);

        if ($template === null) {
            return $this->fallbackResult($speedCategory, $durationCategory);
        }

        return new CommentResult(
            comment: $template,
            source: $this->source(),
            metadata: [
                'speed_category' => $speedCategory,
                'duration_category' => $durationCategory,
                'template_key' => $this->templateKey($speedCategory, $durationCategory),
                'fallback' => false,
            ],
        );
    }

    private function resolveSpeedCategory(?int $charactersPerMinute): ?string
    {
        if ($charactersPerMinute === null) {
            return null;
        }

        $thresholds = $this->config['speed_thresholds'] ?? [];

        $slowMaxExclusive = $thresholds['slow_max_exclusive'] ?? 180;
        $appropriateMinInclusive = $thresholds['appropriate_min_inclusive'] ?? 180;
        $appropriateMaxInclusive = $thresholds['appropriate_max_inclusive'] ?? 320;
        $fastMinExclusive = $thresholds['fast_min_exclusive'] ?? 320;

        if ($charactersPerMinute < $slowMaxExclusive) {
            return 'slow';
        }

        if (
            $charactersPerMinute >= $appropriateMinInclusive
            && $charactersPerMinute <= $appropriateMaxInclusive
        ) {
            return 'appropriate';
        }

        if ($charactersPerMinute > $fastMinExclusive) {
            return 'fast';
        }

        return null;
    }

    private function resolveDurationCategory(?float $durationSeconds): ?string
    {
        if ($durationSeconds === null) {
            return null;
        }

        $thresholds = $this->config['duration_thresholds'] ?? [];

        $shortMaxExclusive = $thresholds['short_max_exclusive'] ?? 30;
        $mediumMinInclusive = $thresholds['medium_min_inclusive'] ?? 30;
        $mediumMaxExclusive = $thresholds['medium_max_exclusive'] ?? 90;
        $longMinInclusive = $thresholds['long_min_inclusive'] ?? 90;

        if ($durationSeconds < $shortMaxExclusive) {
            return 'short';
        }

        if (
            $durationSeconds >= $mediumMinInclusive
            && $durationSeconds < $mediumMaxExclusive
        ) {
            return 'medium';
        }

        if ($durationSeconds >= $longMinInclusive) {
            return 'long';
        }

        return null;
    }

    private function resolveTemplate(string $speedCategory, string $durationCategory): ?string
    {
        $templates = $this->config['templates'][$speedCategory][$durationCategory] ?? null;

        if (! is_array($templates) || $templates === []) {
            return null;
        }

        $template = reset($templates);

        return is_string($template) && $template !== '' ? $template : null;
    }

    private function fallbackResult(?string $speedCategory, ?string $durationCategory): CommentResult
    {
        return new CommentResult(
            comment: $this->fallbackComment(),
            source: $this->source(),
            metadata: [
                'speed_category' => $speedCategory,
                'duration_category' => $durationCategory,
                'template_key' => $speedCategory !== null && $durationCategory !== null
                    ? $this->templateKey($speedCategory, $durationCategory)
                    : null,
                'fallback' => true,
            ],
        );
    }

    private function fallbackComment(): string
    {
        $fallback = $this->config['fallback'] ?? null;

        return is_string($fallback) && $fallback !== ''
            ? $fallback
            : self::FALLBACK_COMMENT;
    }

    private function source(): string
    {
        $source = $this->config['source'] ?? null;

        return is_string($source) && $source !== '' ? $source : 'template';
    }

    private function templateKey(string $speedCategory, string $durationCategory): string
    {
        return $speedCategory.'.'.$durationCategory;
    }
}
