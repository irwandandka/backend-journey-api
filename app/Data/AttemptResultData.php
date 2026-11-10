<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class AttemptResultData extends Data
{
    public function __construct(
        public int $score,
        public int $correctCount,
        public int $questionCount,
        public bool $passed,
        public int $xpEarned,
        public int $xpTotal,
        public int $currentStreak,
        public string $journeyStatus,
        /** @var list<array{position: int, stem: string, dueAt: string}> */
        public array $scheduledForReview,
    ) {}
}
