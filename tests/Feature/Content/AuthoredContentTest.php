<?php

namespace Tests\Feature\Content;

use App\Models\Achievement;
use App\Models\Question;
use App\Models\Track;
use App\Services\AchievementEvaluator;
use App\Services\JourneyUnlock;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthoredContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ContentSeeder::class)->run();
    }

    public function test_every_published_track_has_a_playable_entry_point(): void
    {
        $tracks = Track::where('is_published', true)
            ->with(['journeys' => fn ($q) => $q->where('is_published', true)->orderBy('position')->withCount('quizzes')])
            ->get();

        $this->assertNotEmpty($tracks, 'the authored roadmap has no published track');

        $unlock = app(JourneyUnlock::class);

        foreach ($tracks as $track) {
            $entry = $track->journeys
                ->filter(fn ($journey) => $unlock->isUnlocked($journey, $track->journeys, collect()))
                ->first(fn ($journey) => $journey->quizzes_count > 0);

            $this->assertNotNull(
                $entry,
                "track {$track->slug} has no quiz on any journey a new learner can open, so nothing in it is playable",
            );
        }
    }

    public function test_no_quiz_sits_behind_a_journey_that_cannot_be_completed(): void
    {
        $tracks = Track::where('is_published', true)
            ->with(['journeys' => fn ($q) => $q->where('is_published', true)->orderBy('position')->withCount('quizzes')])
            ->get();

        foreach ($tracks as $track) {
            $journeys = $track->journeys->values();

            foreach ($journeys as $index => $journey) {
                if ($index === 0 || $journey->quizzes_count === 0 || $journey->unlock_rule !== 'sequential') {
                    continue;
                }

                $previous = $journeys[$index - 1];

                $this->assertGreaterThan(
                    0,
                    $previous->quizzes_count,
                    "journey {$journey->slug} needs {$previous->slug} completed, but {$previous->slug} has no quiz to complete",
                );
            }
        }
    }

    public function test_every_authored_question_grades_to_exactly_one_correct_option(): void
    {
        $questions = Question::with('currentVersion.options')->get();

        $this->assertNotEmpty($questions);

        foreach ($questions as $question) {
            $this->assertNotNull($question->current_version_id, "question {$question->key} has no current version");

            $correct = $question->currentVersion->options->where('is_correct', true);

            if ($question->type === 'single_choice') {
                $this->assertCount(1, $correct, "single_choice question {$question->key} must have exactly one correct option");
            } else {
                $this->assertGreaterThan(0, $correct->count(), "question {$question->key} has no correct option");
            }

            $this->assertGreaterThanOrEqual(2, $question->currentVersion->options->count(), "question {$question->key} needs at least two options");
        }
    }

    public function test_every_authored_question_and_option_has_the_fallback_locale(): void
    {
        $fallback = config('app.fallback_locale');

        foreach (Question::with(['currentVersion.translations', 'currentVersion.options.translations'])->get() as $question) {
            $this->assertTrue(
                $question->currentVersion->translations->contains('locale', $fallback),
                "question {$question->key} has no {$fallback} translation to fall back to",
            );

            foreach ($question->currentVersion->options as $option) {
                $this->assertTrue(
                    $option->translations->contains('locale', $fallback),
                    "an option of {$question->key} has no {$fallback} translation to fall back to",
                );
            }
        }
    }

    public function test_every_authored_achievement_rule_is_one_the_evaluator_can_award(): void
    {
        $achievements = Achievement::all();

        $this->assertNotEmpty($achievements);

        foreach ($achievements as $achievement) {
            $this->assertContains(
                $achievement->rule['type'] ?? null,
                AchievementEvaluator::RULE_TYPES,
                "achievement {$achievement->slug} has a rule type the evaluator does not know, so it can never be unlocked",
            );

            $this->assertGreaterThanOrEqual(
                1,
                $achievement->rule['count'] ?? 0,
                "achievement {$achievement->slug} has no reachable target",
            );
        }
    }

    public function test_reseeding_the_authored_file_creates_no_new_version(): void
    {
        $before = \App\Models\QuestionVersion::count();

        app(ContentSeeder::class)->run();

        $this->assertSame($before, \App\Models\QuestionVersion::count(), 'an unchanged content file must not bump any question version');
    }
}
