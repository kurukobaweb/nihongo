<?php

namespace Tests\Unit;

use App\Contracts\CommentGeneratorInterface;
use App\Dto\CommentResult;
use App\Dto\EvaluationResult;
use App\Services\Comment\TemplateCommentGenerator;
use Tests\TestCase;

class TemplateCommentGeneratorTest extends TestCase
{
    public function test_it_implements_comment_generator_interface(): void
    {
        $generator = app(CommentGeneratorInterface::class);

        $this->assertInstanceOf(TemplateCommentGenerator::class, $generator);
        $this->assertInstanceOf(CommentGeneratorInterface::class, $generator);

        $result = $generator->generate(new EvaluationResult(
            durationSeconds: 60.0,
            charactersPerMinute: 240,
        ));

        $this->assertInstanceOf(CommentResult::class, $result);
        $this->assertSame('template', $result->source);
    }

    /**
     * @dataProvider speedCategoryProvider
     */
    public function test_it_resolves_speed_category_boundaries(
        int $charactersPerMinute,
        string $expectedCategory
    ): void {
        $result = $this->generator()->generate(new EvaluationResult(
            durationSeconds: 60.0,
            charactersPerMinute: $charactersPerMinute,
        ));

        $this->assertSame($expectedCategory, $result->metadata['speed_category']);
        $this->assertFalse($result->metadata['fallback']);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function speedCategoryProvider(): array
    {
        return [
            '179 is slow' => [179, 'slow'],
            '180 is appropriate' => [180, 'appropriate'],
            '320 is appropriate' => [320, 'appropriate'],
            '321 is fast' => [321, 'fast'],
        ];
    }

    /**
     * @dataProvider durationCategoryProvider
     */
    public function test_it_resolves_duration_category_boundaries(
        float $durationSeconds,
        string $expectedCategory
    ): void {
        $result = $this->generator()->generate(new EvaluationResult(
            durationSeconds: $durationSeconds,
            charactersPerMinute: 240,
        ));

        $this->assertSame($expectedCategory, $result->metadata['duration_category']);
        $this->assertFalse($result->metadata['fallback']);
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function durationCategoryProvider(): array
    {
        return [
            '29.999 is short' => [29.999, 'short'],
            '30.0 is medium' => [30.0, 'medium'],
            '89.999 is medium' => [89.999, 'medium'],
            '90.0 is long' => [90.0, 'long'],
        ];
    }

    /**
     * @dataProvider templateSelectionProvider
     */
    public function test_it_selects_first_template_for_speed_and_duration(
        int $charactersPerMinute,
        float $durationSeconds,
        string $expectedSpeedCategory,
        string $expectedDurationCategory,
        string $expectedComment
    ): void {
        $result = $this->generator()->generate(new EvaluationResult(
            durationSeconds: $durationSeconds,
            charactersPerMinute: $charactersPerMinute,
        ));

        $this->assertSame($expectedComment, $result->comment);
        $this->assertSame('template', $result->source);
        $this->assertSame($expectedSpeedCategory, $result->metadata['speed_category']);
        $this->assertSame($expectedDurationCategory, $result->metadata['duration_category']);
        $this->assertSame($expectedSpeedCategory.'.'.$expectedDurationCategory, $result->metadata['template_key']);
        $this->assertFalse($result->metadata['fallback']);
    }

    /**
     * @return array<string, array{int, float, string, string, string}>
     */
    public static function templateSelectionProvider(): array
    {
        return [
            'slow short' => [
                100,
                10.0,
                'slow',
                'short',
                'ゆっくり丁寧に話せています。短い発話なので、次は少し長めに話す練習をしてみましょう。',
            ],
            'appropriate medium' => [
                240,
                60.0,
                'appropriate',
                'medium',
                '速さと長さのバランスが良く、聞き取りやすい発話です。この調子で内容の具体性を高めましょう。',
            ],
            'fast long' => [
                400,
                120.0,
                'fast',
                'long',
                '長い内容を話せていますが、全体的に速めです。文の区切りで少し間を取ると、より伝わりやすくなります。',
            ],
        ];
    }

    public function test_it_falls_back_when_characters_per_minute_is_missing(): void
    {
        $result = $this->generator()->generate(new EvaluationResult(durationSeconds: 60.0));

        $this->assertFallbackResult($result);
        $this->assertNull($result->metadata['speed_category']);
        $this->assertSame('medium', $result->metadata['duration_category']);
        $this->assertNull($result->metadata['template_key']);
    }

    public function test_it_falls_back_when_duration_seconds_is_missing(): void
    {
        $result = $this->generator()->generate(new EvaluationResult(charactersPerMinute: 240));

        $this->assertFallbackResult($result);
        $this->assertSame('appropriate', $result->metadata['speed_category']);
        $this->assertNull($result->metadata['duration_category']);
        $this->assertNull($result->metadata['template_key']);
    }

    public function test_it_falls_back_when_template_is_missing(): void
    {
        $config = config('comment_templates');
        unset($config['templates']['slow']['short']);

        $result = (new TemplateCommentGenerator($config))->generate(new EvaluationResult(
            durationSeconds: 10.0,
            charactersPerMinute: 100,
        ));

        $this->assertFallbackResult($result);
        $this->assertSame('slow', $result->metadata['speed_category']);
        $this->assertSame('short', $result->metadata['duration_category']);
        $this->assertSame('slow.short', $result->metadata['template_key']);
    }

    private function generator(): TemplateCommentGenerator
    {
        return new TemplateCommentGenerator(config('comment_templates'));
    }

    private function assertFallbackResult(CommentResult $result): void
    {
        $this->assertSame(
            '評価に必要な情報が一部不足しているため、今回は全体的なコメントのみ表示します。録音内容を確認し、もう一度提出するとより詳しい評価ができます。',
            $result->comment,
        );
        $this->assertSame('template', $result->source);
        $this->assertTrue($result->metadata['fallback']);
    }
}
