<?php

namespace App\Data;

use App\Models\QuizAttempt;
use Spatie\LaravelData\Data;

class AttemptData extends Data
{
    public function __construct(
        public int $id,
        public string $quizSlug,
        public string $status,
        public string $locale,
        public int $questionCount,
        public int $answeredCount,
        public string $startedAt,
    ) {}

    public static function fromModel(QuizAttempt $attempt, int $answeredCount): self
    {
        return new self(
            id: $attempt->id,
            quizSlug: $attempt->quiz->slug,
            status: $attempt->status,
            locale: $attempt->locale,
            questionCount: $attempt->question_count,
            answeredCount: $answeredCount,
            startedAt: $attempt->started_at->toIso8601String(),
        );
    }
}
