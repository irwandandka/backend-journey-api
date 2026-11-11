<?php

namespace App\Data\Admin;

use App\Models\Journey;
use App\Models\Track;
use Spatie\LaravelData\Data;

class TrackTreeData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $abbreviation,
        public string $colorDark,
        public string $colorLight,
        public int $position,
        public bool $isPublished,
        public int $questionCount,
        /** @var list<array{slug: string, title: string, tier: string, unlockRule: string, position: int, isPublished: bool, passThreshold: int, quizzes: list<array{slug: string, questionCount: int}>}> */
        public array $journeys,
    ) {}

    public static function fromModel(Track $track): self
    {
        return new self(
            slug: $track->slug,
            name: (string) $track->t('name'),
            abbreviation: $track->abbreviation,
            colorDark: $track->color_dark,
            colorLight: $track->color_light,
            position: (int) $track->position,
            isPublished: (bool) $track->is_published,
            questionCount: (int) $track->questions_count,
            journeys: $track->journeys->map(fn (Journey $journey) => [
                'slug' => $journey->slug,
                'title' => (string) $journey->t('title'),
                'tier' => $journey->tier,
                'unlockRule' => $journey->unlock_rule,
                'position' => (int) $journey->position,
                'isPublished' => (bool) $journey->is_published,
                'passThreshold' => (int) $journey->pass_threshold,
                'quizzes' => $journey->quizzes->map(fn ($quiz) => [
                    'slug' => $quiz->slug,
                    'questionCount' => (int) $quiz->question_count,
                ])->values()->all(),
            ])->values()->all(),
        );
    }
}
