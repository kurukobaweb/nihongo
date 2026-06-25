<?php

namespace App\Dto;

final readonly class CommentResult
{
    public function __construct(
        public string $comment,
        public string $source,
        public array $metadata = [],
    ) {}
}
