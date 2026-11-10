<?php

namespace App\Data;

use App\Models\Track;
use Spatie\LaravelData\Data;

class TrackSummaryData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public string $abbreviation,
        public string $colorDark,
        public string $colorLight,
        public int $position,
        public int $journeyCount,
        public int $completedCount,
        public int $dueCount,
    ) {}

    public static function fromModel(Track $track, int $completed, int $due): self
    {
        return new self(
            slug: $track->slug,
            name: (string) $track->t('name'),
            description: $track->t('description'),
            abbreviation: $track->abbreviation,
            colorDark: $track->color_dark,
            colorLight: $track->color_light,
            position: $track->position,
            journeyCount: (int) ($track->journeys_count ?? 0),
            completedCount: $completed,
            dueCount: $due,
        );
    }
}
