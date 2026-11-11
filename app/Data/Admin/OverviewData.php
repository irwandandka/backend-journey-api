<?php

namespace App\Data\Admin;

use Spatie\LaravelData\Data;

class OverviewData extends Data
{
    public function __construct(
        /** @var array{total: int, published: int} */
        public array $tracks,
        /** @var array{total: int, published: int, withoutQuiz: int} */
        public array $journeys,
        /** @var array{total: int} */
        public array $quizzes,
        /** @var array{total: int, active: int, withoutVersion: int} */
        public array $questions,
        /** @var list<array{locale: string, translated: int, total: int}> */
        public array $translationCoverage,
        /** @var list<array{slug: string, trackSlug: string, position: int, tier: string}> */
        public array $emptyJourneys,
    ) {}
}
