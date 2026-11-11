<?php

namespace App\Data\Admin;

use App\Models\Question;
use Spatie\LaravelData\Data;

class QuestionSummaryData extends Data
{
    public function __construct(
        public string $key,
        public string $trackSlug,
        public string $type,
        public string $difficulty,
        public bool $isActive,
        public int $version,
        public int $optionCount,
        public int $quizCount,
        public string $stem,
        /** @var list<string> */
        public array $tags,
        /** @var list<string> */
        public array $missingLocales,
        public ?string $updatedAt,
    ) {}

    /** @param list<string> $locales */
    public static function fromModel(Question $question, array $locales): self
    {
        $version = $question->currentVersion;
        $present = $version === null ? [] : $version->translations->pluck('locale')->all();

        return new self(
            key: $question->key,
            trackSlug: $question->track->slug,
            type: $question->type,
            difficulty: $question->difficulty,
            isActive: $question->is_active,
            version: $version?->version ?? 0,
            optionCount: $version?->options->count() ?? 0,
            quizCount: (int) ($question->quizzes_count ?? 0),
            stem: (string) ($version?->translations->firstWhere('locale', $locales[0])?->stem_md
                ?? $version?->translations->first()?->stem_md
                ?? ''),
            tags: $question->tags->pluck('slug')->values()->all(),
            missingLocales: array_values(array_diff($locales, $present)),
            updatedAt: $question->updated_at?->toIso8601String(),
        );
    }
}
