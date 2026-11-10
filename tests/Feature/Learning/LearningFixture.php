<?php

namespace Tests\Feature\Learning;

use App\Models\Journey;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Quiz;
use App\Models\Roadmap;
use App\Models\Track;

class LearningFixture
{
    public static function roadmap(bool $published = true): Roadmap
    {
        $roadmap = Roadmap::create(['slug' => 'backend', 'position' => 1, 'is_published' => $published]);
        $roadmap->translations()->create(['locale' => 'en', 'title' => 'Backend']);
        $roadmap->translations()->create(['locale' => 'id', 'title' => 'Backend Indonesia']);

        return $roadmap;
    }

    public static function track(Roadmap $roadmap, string $slug, bool $published = true, int $position = 1): Track
    {
        $track = Track::create([
            'roadmap_id' => $roadmap->id,
            'slug' => $slug,
            'abbreviation' => strtoupper(substr($slug, 0, 2)),
            'color_dark' => '#00E5FF',
            'color_light' => '#0E7490',
            'position' => $position,
            'is_published' => $published,
        ]);

        $track->translations()->create(['locale' => 'en', 'name' => "Track {$slug}"]);
        $track->translations()->create(['locale' => 'id', 'name' => "Jalur {$slug}"]);

        return $track;
    }

    public static function journey(
        Track $track,
        string $slug,
        int $position,
        string $tier = 'foundation',
        string $rule = 'sequential',
        bool $published = true,
    ): Journey {
        $journey = Journey::create([
            'track_id' => $track->id,
            'slug' => $slug,
            'tier' => $tier,
            'unlock_rule' => $rule,
            'position' => $position,
            'is_published' => $published,
        ]);

        $journey->translations()->create(['locale' => 'en', 'title' => "Journey {$slug}"]);

        return $journey;
    }

    public static function quiz(Journey $journey, int $questionCount = 2): Quiz
    {
        $quiz = Quiz::create([
            'journey_id' => $journey->id,
            'slug' => "{$journey->slug}-quiz",
            'question_count' => $questionCount,
            'shuffle_questions' => false,
            'shuffle_options' => false,
        ]);

        $quiz->translations()->create(['locale' => 'en', 'title' => "Quiz {$journey->slug}"]);

        for ($i = 0; $i < $questionCount; $i++) {
            $quiz->questions()->attach(self::question($journey->track, "q-{$journey->slug}-{$i}")->id, ['position' => $i]);
        }

        return $quiz;
    }

    public static function question(Track $track, string $key, string $difficulty = 'medium'): Question
    {
        $question = Question::create([
            'key' => $key,
            'track_id' => $track->id,
            'type' => 'single_choice',
            'difficulty' => $difficulty,
        ]);

        $version = QuestionVersion::create([
            'question_id' => $question->id,
            'version' => 1,
            'code_language' => 'sql',
            'content_hash' => hash('sha256', $key),
        ]);

        $version->translations()->create([
            'locale' => 'en',
            'stem_md' => "Stem for {$key}",
            'code_snippet' => 'SELECT 1;',
            'explanation_md' => "Explanation for {$key}",
        ]);

        foreach ([false, true, false, false] as $position => $isCorrect) {
            $option = QuestionOption::create([
                'question_version_id' => $version->id,
                'position' => $position,
                'is_correct' => $isCorrect,
            ]);

            $option->translations()->create([
                'locale' => 'en',
                'label_md' => "Option {$position} for {$key}",
                'option_explanation_md' => "Why option {$position} is ".($isCorrect ? 'right' : 'wrong'),
            ]);
        }

        $question->update(['current_version_id' => $version->id]);

        return $question->fresh();
    }
}
