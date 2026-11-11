<?php

namespace App\Data;

use App\Models\Journey;
use Spatie\LaravelData\Data;

class JourneySearchData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $trackSlug,
        public string $trackName,
        public string $tier,
        public int $position,
        public bool $hasQuiz,
    ) {}

    public static function fromModel(Journey $journey): self
    {
        return new self(
            slug: $journey->slug,
            title: (string) $journey->t('title'),
            trackSlug: $journey->track->slug,
            trackName: (string) $journey->track->t('name'),
            tier: $journey->tier,
            position: (int) $journey->position,
            hasQuiz: $journey->quizzes_count > 0,
        );
    }
}
