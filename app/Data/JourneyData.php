<?php

namespace App\Data;

use App\Models\Journey;
use Spatie\LaravelData\Data;

class JourneyData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        public string $tier,
        public int $position,
        public string $status,
        public ?int $bestScore,
        public int $attemptsCount,
        public int $passThreshold,
        public ?string $quizSlug,
        public int $questionCount,
    ) {}

    public static function fromModel(Journey $journey, string $status, ?int $bestScore, int $attempts): self
    {
        $quiz = $journey->relationLoaded('quizzes') ? $journey->quizzes->first() : null;

        return new self(
            slug: $journey->slug,
            title: (string) $journey->t('title'),
            description: $journey->t('description'),
            tier: $journey->tier,
            position: $journey->position,
            status: $status,
            bestScore: $bestScore,
            attemptsCount: $attempts,
            passThreshold: $journey->pass_threshold,
            quizSlug: $quiz?->slug,
            questionCount: (int) ($quiz?->question_count ?? 0),
        );
    }
}
