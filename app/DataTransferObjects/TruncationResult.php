<?php

namespace App\DataTransferObjects;

readonly class TruncationResult
{
    public function __construct(
        public string $text,
        public int $originalCount,
        public int $processedCount,
        public bool $wasTruncated,
    ) {}
}
