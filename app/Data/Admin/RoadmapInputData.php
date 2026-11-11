<?php

namespace App\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class RoadmapInputData extends Data
{
    public function __construct(
        public string $slug,
        public int $position,
        public bool $isPublished,
        #[LiteralTypeScriptType('Record<string, { title: string; description?: string | null }>')]
        /** @var array<string, array<string, string|null>> */
        public array $translations = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'isPublished' => ['required', 'boolean'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.title' => ['required', 'string', 'min:2', 'max:160'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function translationRows(): array
    {
        return collect($this->translations)
            ->map(fn (array $fields) => [
                'title' => (string) $fields['title'],
                'description' => $fields['description'] ?? null,
            ])
            ->all();
    }
}
