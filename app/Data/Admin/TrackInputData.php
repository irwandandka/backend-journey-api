<?php

namespace App\Data\Admin;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class TrackInputData extends Data
{
    public function __construct(
        public string $slug,
        public string $abbreviation,
        public string $colorDark,
        public string $colorLight,
        public int $position,
        public bool $isPublished,
        #[LiteralTypeScriptType('Record<string, { name: string; description?: string | null }>')]
        /** @var array<string, array<string, string|null>> */
        public array $translations = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'abbreviation' => ['required', 'string', 'min:1', 'max:3'],
            'colorDark' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colorLight' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'isPublished' => ['required', 'boolean'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.name' => ['required', 'string', 'min:2', 'max:160'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function translationRows(): array
    {
        return collect($this->translations)
            ->map(fn (array $fields) => [
                'name' => (string) $fields['name'],
                'description' => $fields['description'] ?? null,
            ])
            ->all();
    }
}
