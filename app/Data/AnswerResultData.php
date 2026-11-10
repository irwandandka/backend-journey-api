<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class AnswerResultData extends Data
{
    public function __construct(
        public int $position,
        public bool $isCorrect,
        /** @var list<int> */
        public array $correctOptionIds,
        public ?string $explanation,
        #[LiteralTypeScriptType('Record<number, string>')]
        /** @var array<int, string> */
        public array $optionExplanations,
        public int $xpAwarded,
        public int $answeredCount,
        public int $questionCount,
    ) {}
}
