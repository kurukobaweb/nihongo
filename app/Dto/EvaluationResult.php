<?php

namespace App\Dto;

final readonly class EvaluationResult
{
    public function __construct(
        public string|null $transcript = null,
        public float|null $durationSeconds = null,
        public int|null $charactersPerMinute = null,
        public string|null $speedAssessment = null,
        public float|null $overallScore = null,
        public array|null $pronunciationResult = null,
        public array|null $fluencyResult = null,
        public array $metadata = [],
    ) {}
}
