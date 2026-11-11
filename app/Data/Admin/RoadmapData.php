<?php

namespace App\Data\Admin;

use App\Models\Roadmap;
use Spatie\LaravelData\Data;

class RoadmapData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        public int $position,
        public bool $isPublished,
        public int $trackCount,
    ) {}

    public static function fromModel(Roadmap $roadmap): self
    {
        return new self(
            slug: $roadmap->slug,
            title: (string) $roadmap->t('title'),
            description: $roadmap->t('description'),
            position: (int) $roadmap->position,
            isPublished: (bool) $roadmap->is_published,
            trackCount: (int) ($roadmap->tracks_count ?? 0),
        );
    }
}
