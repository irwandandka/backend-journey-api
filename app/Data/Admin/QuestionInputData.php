<?php

namespace App\Data\Admin;

use App\Http\Middleware\SetLocale;
use App\Models\Question;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class QuestionInputData extends Data
{
    public function __construct(
        public string $key,
        public string $trackSlug,
        public string $type,
        public string $difficulty,
        #[LiteralTypeScriptType('Record<string, { stemMd: string; explanationMd?: string | null; codeSnippet?: string | null }>')]
        /** @var array<string, array<string, string|null>> */
        public array $translations,
        #[LiteralTypeScriptType('Array<{ isCorrect: boolean; translations: Record<string, { labelMd: string; optionExplanationMd?: string | null }> }>')]
        /** @var list<array<string, mixed>> */
        public array $options,
        /** @var list<string> */
        public array $tags = [],
        public bool $isActive = true,
        public ?string $codeLanguage = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'trackSlug' => ['required', 'string', 'exists:tracks,slug'],
            'type' => ['required', 'string', 'in:'.implode(',', Question::TYPES)],
            'difficulty' => ['required', 'string', 'in:'.implode(',', Question::DIFFICULTIES)],
            'isActive' => ['boolean'],
            'codeLanguage' => ['nullable', 'string', 'max:20'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['required', 'string', 'distinct', 'exists:tags,slug'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.stemMd' => ['required', 'string', 'min:10', 'max:4000'],
            'translations.*.explanationMd' => ['nullable', 'string', 'max:4000'],
            'translations.*.codeSnippet' => ['nullable', 'string', 'max:4000'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*.isCorrect' => ['required', 'boolean'],
            'options.*.translations' => ['required', 'array', 'min:1'],
            'options.*.translations.*.labelMd' => ['required', 'string', 'min:1', 'max:1000'],
            'options.*.translations.*.optionExplanationMd' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var array<string, mixed> $input */
            $input = $validator->getData();

            $supported = SetLocale::supportedLocales();
            $fallback = (string) (config('app.fallback_locale') ?: 'en');

            self::assertLocales($validator, 'translations', $input['translations'] ?? [], $supported, $fallback);

            foreach (array_values($input['options'] ?? []) as $index => $option) {
                self::assertLocales(
                    $validator,
                    "options.{$index}.translations",
                    is_array($option) ? ($option['translations'] ?? []) : [],
                    $supported,
                    $fallback,
                );
            }

            $correct = collect($input['options'] ?? [])
                ->filter(fn ($option) => is_array($option) && filter_var($option['isCorrect'] ?? false, FILTER_VALIDATE_BOOL))
                ->count();

            $type = $input['type'] ?? null;

            if ($type === 'multi_choice' && $correct < 1) {
                $validator->errors()->add('options', 'A multi_choice question needs at least one correct option.');
            }

            if (in_array($type, ['single_choice', 'true_false'], true) && $correct !== 1) {
                $validator->errors()->add('options', "A {$type} question needs exactly one correct option, got {$correct}.");
            }
        });
    }

    /**
     * @param  array<string, mixed>  $translations
     * @param  list<string>  $supported
     */
    private static function assertLocales(
        Validator $validator,
        string $field,
        mixed $translations,
        array $supported,
        string $fallback,
    ): void {
        if (! is_array($translations)) {
            return;
        }

        foreach (array_keys($translations) as $locale) {
            if (! in_array($locale, $supported, true)) {
                $validator->errors()->add($field, "Locale {$locale} is not supported.");
            }
        }

        if (! array_key_exists($fallback, $translations)) {
            $validator->errors()->add($field, "The {$fallback} translation is required, because every other locale falls back to it.");
        }
    }

    /**
     * The shape QuestionWriter expects. Keeping the conversion here means the
     * writer stays identical for the seeder and for the admin API.
     *
     * @return array<string, mixed>
     */
    public function toWriterRow(): array
    {
        return [
            'key' => $this->key,
            'track' => $this->trackSlug,
            'type' => $this->type,
            'difficulty' => $this->difficulty,
            'is_active' => $this->isActive,
            'code_language' => $this->codeLanguage,
            'tags' => array_values($this->tags),
            'translations' => collect($this->translations)
                ->map(fn (array $fields) => array_filter([
                    'stem_md' => $fields['stemMd'] ?? null,
                    'explanation_md' => $fields['explanationMd'] ?? null,
                    'code_snippet' => $fields['codeSnippet'] ?? null,
                ], fn ($value) => $value !== null))
                ->all(),
            'options' => collect($this->options)
                ->map(fn (array $option) => [
                    'is_correct' => filter_var($option['isCorrect'] ?? false, FILTER_VALIDATE_BOOL),
                    'translations' => collect($option['translations'] ?? [])
                        ->map(fn (array $fields) => array_filter([
                            'label_md' => $fields['labelMd'] ?? null,
                            'option_explanation_md' => $fields['optionExplanationMd'] ?? null,
                        ], fn ($value) => $value !== null))
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
