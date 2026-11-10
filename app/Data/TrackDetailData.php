<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class TrackDetailData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public string $abbreviation,
        public string $colorDark,
        public string $colorLight,
        /** @var list<JourneyData> */
        public array $journeys,
    ) {}
}
