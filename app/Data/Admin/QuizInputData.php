<?php

namespace App\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class QuizInputData extends Data
{
    public function __construct(
        public string $slug,
        public string $journeySlug,
        public int $questionCount,
        public bool $shuffleQuestions,
        public bool $shuffleOptions,
        public int $position,
        public ?int $timeLimitS = null,
        #[LiteralTypeScriptType('Record<string, { title: string }>')]
        /** @var array<string, array<string, string>> */
        public array $translations = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'journeySlug' => ['required', 'string', 'exists:journeys,slug'],
            'questionCount' => ['required', 'integer', 'min:1', 'max:50'],
            'timeLimitS' => ['nullable', 'integer', 'min:10', 'max:7200'],
            'shuffleQuestions' => ['required', 'boolean'],
            'shuffleOptions' => ['required', 'boolean'],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.title' => ['required', 'string', 'min:2', 'max:160'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function translationRows(): array
    {
        return collect($this->translations)
            ->map(fn (array $fields) => ['title' => (string) $fields['title']])
            ->all();
    }
}
