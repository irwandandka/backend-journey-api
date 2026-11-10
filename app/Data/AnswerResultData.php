<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class AnswerResultData extends Data
{
    public function __construct(
        public int $position,
        public bool $isCorrect,
        /** @var list<int> */
        public array $correctOptionIds,
        public ?string $explanation,
        /** @var array<int, string> */
        public array $optionExplanations,
        public int $xpAwarded,
        public int $answeredCount,
        public int $questionCount,
    ) {}
}
