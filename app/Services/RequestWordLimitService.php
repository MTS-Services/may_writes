<?php

namespace App\Services;

use App\DataTransferObjects\TruncationResult;
use App\Models\Plan;

class RequestWordLimitService
{
    public function limitForPlan(?Plan $plan): ?int
    {
        if ($plan === null) {
            return null;
        }

        return $plan->words_per_request;
    }

    public function countWords(string $text): int
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return 0;
        }

        $parts = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? 0 : count($parts);
    }

    public function applyLimit(string $text, ?int $limit): TruncationResult
    {
        $originalCount = $this->countWords($text);

        if ($limit === null || $originalCount <= $limit) {
            return new TruncationResult(
                text: $text,
                originalCount: $originalCount,
                processedCount: $originalCount,
                wasTruncated: false,
            );
        }

        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $truncated = implode(' ', array_slice($parts, 0, $limit));

        return new TruncationResult(
            text: $truncated,
            originalCount: $originalCount,
            processedCount: $limit,
            wasTruncated: true,
        );
    }

    public function truncationNotice(?Plan $plan, int $limit): string
    {
        $planName = $plan?->name ?? 'your plan';

        return "Your {$planName} plan allows {$limit} words per request (description only). We kept the first {$limit} words in your card description. Please submit additional content as a new card.";
    }
}
