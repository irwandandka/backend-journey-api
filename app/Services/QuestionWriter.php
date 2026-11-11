<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Tag;
use App\Models\Track;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The one place a question is written. Both the content seeder and the admin
 * API go through here, so "did the content change" has a single definition.
 */
class QuestionWriter
{
    /** @var array<string, int> */
    private array $trackIds = [];

    /** @var array<string, int> */
    private array $tagIds = [];

    /**
     * @param  array<string, mixed>  $row
     */
    public function save(array $row): Question
    {
        return DB::transaction(function () use ($row) {
            $question = Question::updateOrCreate(
                ['key' => $row['key']],
                [
                    'track_id' => $this->trackId((string) $row['track'], (string) $row['key']),
                    'type' => $row['type'] ?? 'single_choice',
                    'difficulty' => $row['difficulty'] ?? 'medium',
                    'is_active' => $row['is_active'] ?? true,
                ],
            );

            $hash = $this->contentHash($row);
            $current = $question->currentVersion;

            if ($current === null || $current->content_hash !== $hash) {
                $this->writeVersion($question, $row, $hash);
            }

            $question->tags()->sync($this->tagIdsFor($row['tags'] ?? []));

            return $question->refresh();
        });
    }

    /**
     * The hash covers only what a learner sees. Difficulty and is_active are
     * metadata, so changing them must not invalidate a served version.
     *
     * Keys are sorted first: the JSON content file and the admin API build the
     * same content in different key orders, and an unsorted hash would make
     * every admin save look like an edit.
     *
     * @param  array<string, mixed>  $row
     */
    public function contentHash(array $row): string
    {
        return hash('sha256', json_encode([
            $row['type'] ?? 'single_choice',
            $row['code_language'] ?? null,
            self::normalize($row['translations']),
            self::normalize($row['options']),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Sorts the keys of every map while leaving list order alone, because the
     * position of an option is content and the order of locales is not.
     */
    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $value = array_map(self::normalize(...), $value);

        if (! $isList) {
            ksort($value);
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function writeVersion(Question $question, array $row, string $hash): void
    {
        $version = QuestionVersion::create([
            'question_id' => $question->id,
            'version' => (int) $question->versions()->max('version') + 1,
            'code_language' => $row['code_language'] ?? null,
            'content_hash' => $hash,
        ]);

        $this->syncTranslations($version, $row['translations']);

        foreach (array_values($row['options']) as $position => $optionRow) {
            $option = QuestionOption::create([
                'question_version_id' => $version->id,
                'position' => $position,
                'is_correct' => $optionRow['is_correct'],
            ]);

            $this->syncTranslations($option, $optionRow['translations']);
        }

        $question->update(['current_version_id' => $version->id]);
    }

    private function trackId(string $slug, string $key): int
    {
        if (! array_key_exists($slug, $this->trackIds)) {
            $id = Track::where('slug', $slug)->value('id');

            if ($id === null) {
                throw new RuntimeException("Unknown track for question {$key}: {$slug}");
            }

            $this->trackIds[$slug] = (int) $id;
        }

        return $this->trackIds[$slug];
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function tagIdsFor(array $slugs): array
    {
        $missing = array_values(array_diff($slugs, array_keys($this->tagIds)));

        if ($missing !== []) {
            foreach (Tag::whereIn('slug', $missing)->pluck('id', 'slug') as $slug => $id) {
                $this->tagIds[$slug] = (int) $id;
            }
        }

        return array_map(function (string $slug): int {
            return $this->tagIds[$slug] ?? throw new RuntimeException("Unknown tag: {$slug}");
        }, array_values($slugs));
    }

    /** @param array<string, array<string, mixed>> $translations */
    private function syncTranslations(object $model, array $translations): void
    {
        foreach ($translations as $locale => $fields) {
            $model->translations()->updateOrCreate(['locale' => $locale], $fields);
        }
    }
}
