<?php

namespace App\DataTransferObjects;

readonly class CardContent
{
    public function __construct(
        public string $title,
        public string $description,
        public string $aggregatedForAi,
    ) {}
}
