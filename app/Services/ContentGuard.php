<?php

namespace App\Services;

use App\Http\Middleware\SetLocale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The refusals the admin surface shares. Everything here answers the same
 * question: would this write leave content that cannot be served, or destroy
 * something a learner has already seen?
 */
class ContentGuard
{
    /** @param array<string, mixed> $translations */
    public function assertFallbackLocale(array $translations, string $field): void
    {
        $supported = SetLocale::supportedLocales();
        $fallback = (string) (config('app.fallback_locale') ?: 'en');

        foreach (array_keys($translations) as $locale) {
            if (! in_array($locale, $supported, true)) {
                throw ValidationException::withMessages([$field => "Locale {$locale} is not supported."]);
            }
        }

        if (! array_key_exists($fallback, $translations)) {
            throw ValidationException::withMessages([
                $field => "The {$fallback} translation is required, because every other locale falls back to it.",
            ]);
        }
    }

    public function assertSameSlug(string $sent, string $current, string $label): void
    {
        if ($sent !== $current) {
            throw ValidationException::withMessages([
                'slug' => "A {$label} slug is a stable id and cannot be changed.",
            ]);
        }
    }

    /** @param class-string<Model> $model */
    public function assertSlugFree(string $model, string $slug, string $label): void
    {
        if ($model::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => "A {$label} with the slug {$slug} already exists."]);
        }
    }

    public function refuseIf(bool $condition, string $field, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
