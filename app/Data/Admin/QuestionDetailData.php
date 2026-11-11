<?php

namespace App\Data\Admin;

use App\Models\Question;
use Spatie\LaravelData\Data;

class QuestionDetailData extends Data
{
    public function __construct(
        public string $key,
        public string $trackSlug,
        public string $type,
        public string $difficulty,
        public bool $isActive,
        public ?string $codeLanguage,
        public int $version,
        public int $quizCount,
        /** @var list<string> */
        public array $tags,
        /** @var array<string, array{stemMd: string, explanationMd: string|null, codeSnippet: string|null}> */
        public array $translations,
        /** @var list<array{isCorrect: bool, translations: array<string, array{labelMd: string, optionExplanationMd: string|null}>}> */
        public array $options,
        /** @var list<array{version: int, servedCount: int, isCurrent: bool}> */
        public array $history,
    ) {}

    public static function fromModel(Question $question): self
    {
        $version = $question->currentVersion;

        $translations = [];

        foreach ($version?->translations ?? [] as $row) {
            $translations[$row->locale] = [
                'stemMd' => (string) $row->stem_md,
                'explanationMd' => $row->explanation_md,
                'codeSnippet' => $row->code_snippet,
            ];
        }

        $options = [];

        foreach ($version?->options ?? [] as $option) {
            $labels = [];

            foreach ($option->translations as $row) {
                $labels[$row->locale] = [
                    'labelMd' => (string) $row->label_md,
                    'optionExplanationMd' => $row->option_explanation_md,
                ];
            }

            $options[] = ['isCorrect' => (bool) $option->is_correct, 'translations' => $labels];
        }

        $history = $question->versions
            ->map(fn ($row) => [
                'version' => (int) $row->version,
                'servedCount' => (int) ($row->attempt_questions_count ?? 0),
                'isCurrent' => $row->id === $question->current_version_id,
            ])
            ->values()
            ->all();

        return new self(
            key: $question->key,
            trackSlug: $question->track->slug,
            type: $question->type,
            difficulty: $question->difficulty,
            isActive: $question->is_active,
            codeLanguage: $version?->code_language,
            version: $version?->version ?? 0,
            quizCount: (int) ($question->quizzes_count ?? 0),
            tags: $question->tags->pluck('slug')->values()->all(),
            translations: $translations,
            options: $options,
            history: $history,
        );
    }
}
