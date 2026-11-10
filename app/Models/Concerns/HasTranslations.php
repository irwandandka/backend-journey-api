<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

trait HasTranslations
{
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel(), $this->translationForeignKey());
    }

    /** @return class-string<Model> */
    public function translationModel(): string
    {
        return static::class.'Translation';
    }

    public function translationForeignKey(): string
    {
        return Str::snake(class_basename(static::class)).'_id';
    }

    public function scopeWithTranslations(Builder $query, ?string $locale = null): void
    {
        $chain = static::localeChain($locale);

        $query->with([
            'translations' => fn (HasMany $inner) => $inner->whereIn('locale', $chain),
        ]);
    }

    public function translation(?string $locale = null): ?Model
    {
        $chain = static::localeChain($locale);

        $loaded = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->whereIn('locale', $chain)->get();

        foreach ($chain as $candidate) {
            $hit = $loaded->firstWhere('locale', $candidate);

            if ($hit !== null) {
                return $hit;
            }
        }

        return $loaded->first();
    }

    public function t(string $attribute, ?string $locale = null): ?string
    {
        $value = $this->translation($locale)?->getAttribute($attribute);

        return $value === null ? null : (string) $value;
    }

    /** @return list<string> */
    public static function localeChain(?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $fallback = (string) (config('app.fallback_locale') ?: 'en');

        return array_values(array_unique([$locale, $fallback]));
    }
}
