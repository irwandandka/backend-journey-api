<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ReviewSummaryData extends Data
{
    public function __construct(
        public int $dueCount,
        public int $trackedCount,
        public ?string $nextDueAt,
        public int $sessionSize,
    ) {}
}
