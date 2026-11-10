<?php

namespace App\Data;

use App\Models\QuizAttemptQuestion;
use Spatie\LaravelData\Data;

class ServedQuestionData extends Data
{
    public function __construct(
        public int $position,
        public string $stem,
        public ?string $codeSnippet,
        public ?string $codeLanguage,
        public bool $answered,
        /** @var list<array{id: int, label: string}> */
        public array $options,
    ) {}

    public static function fromAttemptQuestion(QuizAttemptQuestion $served): self
    {
        $snapshot = $served->snapshot;

        return new self(
            position: $served->position,
            stem: (string) ($snapshot['stem_md'] ?? ''),
            codeSnippet: $snapshot['code_snippet'] ?? null,
            codeLanguage: $snapshot['code_language'] ?? null,
            answered: $served->relationLoaded('answer') ? $served->answer !== null : $served->answer()->exists(),
            options: array_map(
                fn (array $option) => ['id' => (int) $option['id'], 'label' => (string) $option['label_md']],
                $snapshot['options'] ?? [],
            ),
        );
    }
}
