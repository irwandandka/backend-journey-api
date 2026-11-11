<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class InsightData extends Data
{
    public function __construct(
        /** @var array{answered: int, correct: int, accuracy: int|null, tracked: int} */
        public array $overall,
        /** @var list<array{slug: string, name: string, answered: int, correct: int, accuracy: int}> */
        public array $byTag,
        /** @var list<array{slug: string, name: string, answered: int, correct: int, accuracy: int}> */
        public array $byTrack,
        /** @var list<array{key: string, stem: string, lapses: int, dueAt: string, trackSlug: string}> */
        public array $hardestQuestions,
    ) {}
}
