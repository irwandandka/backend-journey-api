<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Journey;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Roadmap;
use App\Models\Tag;
use App\Models\Track;
use App\Services\QuestionWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContentSeeder extends Seeder
{
    public function __construct(private readonly QuestionWriter $questions) {}

    public function run(string $file = 'backend-engineering.json'): void
    {
        $path = database_path("content/{$file}");

        if (! is_file($path)) {
            throw new RuntimeException("Content file not found: {$path}");
        }

        $this->sync(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $content */
    public function sync(array $content): void
    {
        DB::transaction(function () use ($content) {
            $roadmap = $this->syncRoadmap($content['roadmap']);
            $tags = $this->syncTags($content['tags'] ?? []);
            $tracks = $this->syncTracks($roadmap, $content['tracks'] ?? []);
            $questions = $this->syncQuestions($tracks, $tags, $content['questions'] ?? []);
            $this->syncQuizzes($tracks, $questions, $content['tracks'] ?? []);
            $this->syncAchievements($content['achievements'] ?? []);
        });
    }

    /** @param array<string, mixed> $data */
    private function syncRoadmap(array $data): Roadmap
    {
        $roadmap = Roadmap::updateOrCreate(
            ['slug' => $data['slug']],
            ['position' => $data['position'] ?? 0, 'is_published' => $data['is_published'] ?? false],
        );

        $this->syncTranslations($roadmap, $data['translations'] ?? []);

        return $roadmap;
    }

    /**
     * @param  list<array{slug: string, name: string}>  $rows
     * @return array<string, Tag>
     */
    private function syncTags(array $rows): array
    {
        $tags = [];

        foreach ($rows as $row) {
            $tags[$row['slug']] = Tag::updateOrCreate(['slug' => $row['slug']], ['name' => $row['name']]);
        }

        return $tags;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, Track>
     */
    private function syncTracks(Roadmap $roadmap, array $rows): array
    {
        $tracks = [];

        foreach ($rows as $row) {
            $track = Track::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'roadmap_id' => $roadmap->id,
                    'abbreviation' => $row['abbreviation'],
                    'color_dark' => $row['color_dark'],
                    'color_light' => $row['color_light'],
                    'position' => $row['position'] ?? 0,
                    'is_published' => $row['is_published'] ?? false,
                ],
            );

            $this->syncTranslations($track, $row['translations'] ?? []);

            foreach ($row['journeys'] ?? [] as $journeyRow) {
                $journey = Journey::updateOrCreate(
                    ['slug' => $journeyRow['slug']],
                    [
                        'track_id' => $track->id,
                        'tier' => $journeyRow['tier'] ?? 'foundation',
                        'unlock_rule' => $journeyRow['unlock_rule'] ?? 'sequential',
                        'position' => $journeyRow['position'] ?? 0,
                        'pass_threshold' => $journeyRow['pass_threshold'] ?? 70,
                        'is_published' => $journeyRow['is_published'] ?? true,
                    ],
                );

                $this->syncTranslations($journey, $journeyRow['translations'] ?? []);
            }

            $tracks[$row['slug']] = $track;
        }

        return $tracks;
    }

    /**
     * @param  array<string, Track>  $tracks
     * @param  array<string, Tag>  $tags
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, Question>
     */
    private function syncQuestions(array $tracks, array $tags, array $rows): array
    {
        $questions = [];

        foreach ($rows as $row) {
            if (! isset($tracks[$row['track']])) {
                throw new RuntimeException("Unknown track for question {$row['key']}: {$row['track']}");
            }

            foreach ($row['tags'] ?? [] as $slug) {
                if (! isset($tags[$slug])) {
                    throw new RuntimeException("Unknown tag: {$slug}");
                }
            }

            $questions[$row['key']] = $this->questions->save($row);
        }

        return $questions;
    }

    /**
     * @param  array<string, Track>  $tracks
     * @param  array<string, Question>  $questions
     * @param  list<array<string, mixed>>  $trackRows
     */
    private function syncQuizzes(array $tracks, array $questions, array $trackRows): void
    {
        foreach ($trackRows as $trackRow) {
            foreach ($trackRow['journeys'] ?? [] as $journeyRow) {
                if (! isset($journeyRow['quiz'])) {
                    continue;
                }

                $quizRow = $journeyRow['quiz'];
                $journey = Journey::where('slug', $journeyRow['slug'])->sole();

                $quiz = Quiz::updateOrCreate(
                    ['slug' => $quizRow['slug']],
                    [
                        'journey_id' => $journey->id,
                        'question_count' => $quizRow['question_count'] ?? count($quizRow['questions']),
                        'time_limit_s' => $quizRow['time_limit_s'] ?? null,
                        'shuffle_questions' => $quizRow['shuffle_questions'] ?? true,
                        'shuffle_options' => $quizRow['shuffle_options'] ?? true,
                        'position' => $quizRow['position'] ?? 0,
                    ],
                );

                $this->syncTranslations($quiz, $quizRow['translations'] ?? []);

                $pivot = [];

                foreach (array_values($quizRow['questions']) as $position => $key) {
                    $question = $questions[$key]
                        ?? throw new RuntimeException("Quiz {$quizRow['slug']} references unknown question {$key}");

                    $pivot[$question->id] = ['position' => $position];
                }

                $quiz->questions()->sync($pivot);
            }
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function syncAchievements(array $rows): void
    {
        foreach ($rows as $row) {
            $achievement = Achievement::updateOrCreate(
                ['slug' => $row['slug']],
                ['icon' => $row['icon'], 'rule' => $row['rule'], 'position' => $row['position'] ?? 0],
            );

            $this->syncTranslations($achievement, $row['translations'] ?? []);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function syncTranslations(object $model, array $translations): void
    {
        foreach ($translations as $locale => $fields) {
            $model->translations()->updateOrCreate(['locale' => $locale], $fields);
        }
    }
}
