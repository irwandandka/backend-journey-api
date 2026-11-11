<?php

namespace App\Services;

class TranslationWriter
{
    /**
     * @param  array<string, array<string, mixed>>  $translations  keyed by locale
     */
    public function sync(object $model, array $translations): void
    {
        foreach ($translations as $locale => $fields) {
            $model->translations()->updateOrCreate(['locale' => $locale], $fields);
        }
    }

    /**
     * Drops locales the caller no longer sends, which is what an admin form
     * means when it clears a translation column.
     *
     * @param  array<string, array<string, mixed>>  $translations
     */
    public function replace(object $model, array $translations): void
    {
        $this->sync($model, $translations);

        $model->translations()->whereNotIn('locale', array_keys($translations))->delete();
    }
}
