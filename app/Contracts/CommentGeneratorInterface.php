<?php

namespace App\Contracts;

use App\Dto\CommentResult;
use App\Dto\EvaluationResult;

interface CommentGeneratorInterface
{
    public function generate(EvaluationResult $evaluationResult): CommentResult;
}
