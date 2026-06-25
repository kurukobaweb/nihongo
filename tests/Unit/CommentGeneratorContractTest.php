<?php

namespace Tests\Unit;

use App\Contracts\CommentGeneratorInterface;
use App\Dto\CommentResult;
use App\Dto\EvaluationResult;
use PHPUnit\Framework\TestCase;

class CommentGeneratorContractTest extends TestCase
{
    public function test_evaluation_result_holds_comment_generation_inputs(): void
    {
        $result = new EvaluationResult(
            transcript: 'sample transcript',
            durationSeconds: 12.5,
            charactersPerMinute: 120,
            speedAssessment: 'standard',
            overallScore: 87.5,
            pronunciationResult: ['accuracy' => 90],
            fluencyResult: ['score' => 85],
            metadata: ['submission_id' => 123],
        );

        $this->assertSame('sample transcript', $result->transcript);
        $this->assertSame(12.5, $result->durationSeconds);
        $this->assertSame(120, $result->charactersPerMinute);
        $this->assertSame('standard', $result->speedAssessment);
        $this->assertSame(87.5, $result->overallScore);
        $this->assertSame(['accuracy' => 90], $result->pronunciationResult);
        $this->assertSame(['score' => 85], $result->fluencyResult);
        $this->assertSame(['submission_id' => 123], $result->metadata);
    }

    public function test_comment_result_holds_generated_comment_output(): void
    {
        $result = new CommentResult(
            comment: 'result value',
            source: 'unit-test',
            metadata: ['version' => 1],
        );

        $this->assertSame('result value', $result->comment);
        $this->assertSame('unit-test', $result->source);
        $this->assertSame(['version' => 1], $result->metadata);
    }

    public function test_comment_generator_interface_can_be_implemented(): void
    {
        $generator = new class implements CommentGeneratorInterface
        {
            public function generate(EvaluationResult $evaluationResult): CommentResult
            {
                return new CommentResult(
                    comment: $evaluationResult->transcript ?? '',
                    source: 'unit-test',
                );
            }
        };

        $result = $generator->generate(new EvaluationResult(transcript: 'contract-check'));

        $this->assertSame('contract-check', $result->comment);
        $this->assertSame('unit-test', $result->source);
    }
}
