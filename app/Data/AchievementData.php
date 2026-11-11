<?php

namespace App\Data;

use App\Models\Achievement;
use Spatie\LaravelData\Data;

class AchievementData extends Data
{
    public function __construct(
        public string $slug,
        public string $icon,
        public string $title,
        public ?string $description,
        public ?string $unlockedAt,
    ) {}

    public static function fromModel(Achievement $achievement, ?string $unlockedAt = null): self
    {
        return new self(
            slug: $achievement->slug,
            icon: $achievement->icon,
            title: (string) $achievement->t('title'),
            description: $achievement->t('description'),
            unlockedAt: $unlockedAt,
        );
    }
}
