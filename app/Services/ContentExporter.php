<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Journey;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\Roadmap;
use App\Models\Tag;
use App\Models\Track;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Turns the database back into the JSON the content seeder reads, so the file
 * in database/content stays a faithful copy of what the admin has authored.
 */
class ContentExporter
{
    /** @return array<string, mixed> */
    public function export(?string $roadmapSlug = null): array
    {
        $roadmap = Roadmap::query()
            ->when($roadmapSlug !== null, fn ($q) => $q->where('slug', $roadmapSlug))
            ->with('translations')
            ->orderBy('position')
            ->first() ?? throw new RuntimeException('There is no roadmap to export.');

        return [
            'roadmap' => [
                'slug' => $roadmap->slug,
                'position' => (int) $roadmap->position,
                'is_published' => (bool) $roadmap->is_published,
                'translations' => $this->translations($roadmap, ['title', 'description']),
            ],
            'tags' => Tag::orderBy('slug')->get()->map(fn (Tag $tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
            ])->all(),
            'tracks' => $this->tracks($roadmap),
            'questions' => $this->questions(),
            'achievements' => $this->achievements(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tracks(Roadmap $roadmap): array
    {
        return Track::where('roadmap_id', $roadmap->id)
            ->with([
                'translations',
                'journeys' => fn ($q) => $q->orderBy('position')->with([
                    'translations',
                    'quizzes' => fn ($inner) => $inner->orderBy('position')->with([
                        'translations',
                        'questions:id,key',
                    ]),
                ]),
            ])
            ->orderBy('position')
            ->get()
            ->map(fn (Track $track) => [
                'slug' => $track->slug,
                'abbreviation' => $track->abbreviation,
                'color_dark' => $track->color_dark,
                'color_light' => $track->color_light,
                'position' => (int) $track->position,
                'is_published' => (bool) $track->is_published,
                'translations' => $this->translations($track, ['name', 'description']),
                'journeys' => $track->journeys->map(fn (Journey $journey) => array_filter([
                    'slug' => $journey->slug,
                    'tier' => $journey->tier,
                    'unlock_rule' => $journey->unlock_rule,
                    'position' => (int) $journey->position,
                    'pass_threshold' => (int) $journey->pass_threshold,
                    'is_published' => (bool) $journey->is_published,
                    'translations' => $this->translations($journey, ['title', 'description']),
                    'quizzes' => $journey->quizzes->map(fn (Quiz $quiz) => [
                        'slug' => $quiz->slug,
                        'question_count' => (int) $quiz->question_count,
                        'time_limit_s' => $quiz->time_limit_s === null ? null : (int) $quiz->time_limit_s,
                        'shuffle_questions' => (bool) $quiz->shuffle_questions,
                        'shuffle_options' => (bool) $quiz->shuffle_options,
                        'position' => (int) $quiz->position,
                        'translations' => $this->translations($quiz, ['title']),
                        'questions' => $quiz->questions->pluck('key')->values()->all(),
                    ])->values()->all(),
                ], fn (mixed $value) => $value !== []))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function questions(): array
    {
        return Question::query()
            ->whereNotNull('current_version_id')
            ->with([
                'track:id,slug',
                'tags:id,slug',
                'currentVersion.translations',
                'currentVersion.options.translations',
            ])
            ->orderBy('key')
            ->get()
            ->map(fn (Question $question) => [
                'key' => $question->key,
                'track' => $question->track->slug,
                'type' => $question->type,
                'difficulty' => $question->difficulty,
                'is_active' => (bool) $question->is_active,
                'code_language' => $question->currentVersion->code_language,
                'tags' => $question->tags->pluck('slug')->values()->all(),
                'translations' => $this->translations(
                    $question->currentVersion,
                    ['stem_md', 'explanation_md', 'code_snippet'],
                ),
                'options' => $question->currentVersion->options
                    ->map(fn (QuestionOption $option) => [
                        'is_correct' => (bool) $option->is_correct,
                        'translations' => $this->translations($option, ['label_md', 'option_explanation_md']),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function achievements(): array
    {
        return Achievement::with('translations')
            ->orderBy('position')
            ->get()
            ->map(fn (Achievement $achievement) => [
                'slug' => $achievement->slug,
                'icon' => $achievement->icon,
                'position' => (int) $achievement->position,
                'rule' => $achievement->rule,
                'translations' => $this->translations($achievement, ['title', 'description']),
            ])
            ->values()
            ->all();
    }

    /**
     * Null fields are dropped rather than written as null, so a re-import
     * produces the same content hash as the export came from.
     *
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function translations(Model $model, array $fields): array
    {
        $rows = [];

        foreach ($model->translations->sortBy('locale') as $row) {
            $values = [];

            foreach ($fields as $field) {
                $value = $row->getAttribute($field);

                if ($value === null) continue;

                $values[$field] = $value;
            }

            $rows[$row->locale] = $values;
        }

        return $rows;
    }
}
