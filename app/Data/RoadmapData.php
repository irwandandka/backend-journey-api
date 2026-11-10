<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class RoadmapData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        /** @var list<TrackSummaryData> */
        public array $tracks,
    ) {}
}
