<?php

namespace Tests\Feature\Learning;

use App\Models\Achievement;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReviewItem;
use App\Models\Track;
use App\Models\User;
use App\Services\AchievementEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Track $track;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $roadmap = LearningFixture::roadmap();
        $this->track = LearningFixture::track($roadmap, 'indexing');
        $journey = LearningFixture::journey($this->track, 'composite-index', 1);
        $this->quiz = LearningFixture::quiz($journey, questionCount: 1);
    }

    private function achievement(string $slug, array $rule, int $position = 1): Achievement
    {
        $achievement = Achievement::create([
            'slug' => $slug,
            'icon' => 'flag',
            'rule' => $rule,
            'position' => $position,
        ]);

        $achievement->translations()->create(['locale' => 'en', 'title' => "Title {$slug}", 'description' => "Desc {$slug}"]);
        $achievement->translations()->create(['locale' => 'id', 'title' => "Judul {$slug}"]);

        return $achievement;
    }

    private function unlockedSlugs(): array
    {
        return DB::table('user_achievements')
            ->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->where('user_achievements.user_id', $this->user->id)
            ->pluck('achievements.slug')
            ->sort()
            ->values()
            ->all();
    }

    private function passTheQuiz(): array
    {
        $attemptId = $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$this->quiz->slug}/attempts")
            ->assertCreated()
            ->json('data.id');

        $served = QuizAttempt::find($attemptId)
            ->attemptQuestions()
            ->with('questionVersion.options')
            ->get();

        foreach ($served as $row) {
            $this->postJson("/api/v1/attempts/{$attemptId}/answers", [
                'position' => $row->position,
                'optionIds' => [$row->questionVersion->options->firstWhere('is_correct', true)->id],
            ])->assertOk();
        }

        return $this->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk()->json('data');
    }

    public function test_completing_a_journey_unlocks_the_first_journey_achievement_and_reports_it_once(): void
    {
        $this->achievement('first-journey', ['type' => 'journeys_completed', 'count' => 1]);

        $result = $this->passTheQuiz();

        $this->assertSame('completed', $result['journeyStatus']);
        $this->assertSame(['first-journey'], array_column($result['unlockedAchievements'], 'slug'));
        $this->assertSame('Title first-journey', $result['unlockedAchievements'][0]['title']);
        $this->assertNotNull($result['unlockedAchievements'][0]['unlockedAt']);
        $this->assertSame(['first-journey'], $this->unlockedSlugs());

        $again = $this->passTheQuiz();

        $this->assertSame([], $again['unlockedAchievements'], 'an already unlocked achievement must not be reported again');
        $this->assertSame(['first-journey'], $this->unlockedSlugs());
        $this->assertSame(1, DB::table('user_achievements')->where('user_id', $this->user->id)->count());
    }

    public function test_an_achievement_the_user_has_not_earned_stays_locked(): void
    {
        $this->achievement('ten-journeys', ['type' => 'journeys_completed', 'count' => 10]);

        $result = $this->passTheQuiz();

        $this->assertSame([], $result['unlockedAchievements']);
        $this->assertSame([], $this->unlockedSlugs());
    }

    public function test_a_streak_achievement_reads_the_longest_streak_so_it_survives_a_broken_run(): void
    {
        $this->achievement('week-streak', ['type' => 'streak_days', 'count' => 7]);

        $this->user->forceFill(['current_streak' => 2, 'longest_streak' => 9])->save();

        $unlocked = app(AchievementEvaluator::class)->evaluate($this->user->fresh());

        $this->assertSame(['week-streak'], $unlocked->pluck('slug')->all());
    }

    public function test_a_streak_achievement_stays_locked_below_its_target(): void
    {
        $this->achievement('week-streak', ['type' => 'streak_days', 'count' => 7]);

        $this->user->forceFill(['current_streak' => 6, 'longest_streak' => 6])->save();

        $this->assertCount(0, app(AchievementEvaluator::class)->evaluate($this->user->fresh()));
    }

    public function test_an_empty_review_queue_only_counts_as_cleared_when_something_was_tracked(): void
    {
        $this->achievement('empty-queue', ['type' => 'review_queue_cleared', 'count' => 1]);

        $this->assertCount(
            0,
            app(AchievementEvaluator::class)->evaluate($this->user->fresh()),
            'a user who never missed a question has not cleared anything',
        );

        $question = Question::where('track_id', $this->track->id)->first();

        ReviewItem::create([
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'due_at' => now()->addDays(3),
            'interval_days' => 3,
            'ease_factor' => 2.5,
            'repetitions' => 1,
            'lapses' => 1,
        ]);

        $this->assertSame(
            ['empty-queue'],
            app(AchievementEvaluator::class)->evaluate($this->user->fresh())->pluck('slug')->all(),
        );
    }

    public function test_a_due_review_item_blocks_the_cleared_queue_achievement(): void
    {
        $this->achievement('empty-queue', ['type' => 'review_queue_cleared', 'count' => 1]);

        $question = Question::where('track_id', $this->track->id)->first();

        ReviewItem::create([
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'due_at' => now()->subHour(),
            'interval_days' => 1,
            'ease_factor' => 2.5,
            'repetitions' => 0,
            'lapses' => 1,
        ]);

        $this->assertCount(0, app(AchievementEvaluator::class)->evaluate($this->user->fresh()));
    }

    public function test_a_rule_type_the_evaluator_does_not_know_never_unlocks(): void
    {
        $this->achievement('mystery', ['type' => 'questions_per_minute', 'count' => 1]);
        $this->achievement('no-target', ['type' => 'journeys_completed', 'count' => 0], 2);

        $this->passTheQuiz();

        $this->assertSame([], $this->unlockedSlugs());
    }

    public function test_evaluating_a_fully_unlocked_account_asks_the_database_for_no_metrics(): void
    {
        $this->achievement('first-journey', ['type' => 'journeys_completed', 'count' => 1]);
        $this->achievement('week-streak', ['type' => 'streak_days', 'count' => 1], 2);

        $this->user->forceFill(['current_streak' => 3, 'longest_streak' => 3])->save();
        $this->passTheQuiz();

        $this->assertSame(['first-journey', 'week-streak'], $this->unlockedSlugs());

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(AchievementEvaluator::class)->evaluate($this->user->fresh());
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertCount(
            1,
            $queries->filter(fn (string $q) => str_contains($q, 'achievements')),
            'with nothing left to earn the evaluator should load candidates once and stop: '.$queries->implode(' | '),
        );
        $this->assertCount(
            0,
            $queries->filter(fn (string $q) => str_contains($q, 'user_journey_progress') || str_contains($q, 'review_items')),
            'no metric should be computed when no rule asks for it',
        );
    }

    public function test_the_index_lists_locked_and_unlocked_side_by_side_in_the_users_locale(): void
    {
        $this->achievement('first-journey', ['type' => 'journeys_completed', 'count' => 1], 1);
        $this->achievement('ten-journeys', ['type' => 'journeys_completed', 'count' => 10], 2);

        $this->passTheQuiz();

        $this->actingAs($this->user)->getJson('/api/v1/achievements?locale=id')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'first-journey')
            ->assertJsonPath('data.0.title', 'Judul first-journey')
            ->assertJsonPath('data.1.slug', 'ten-journeys')
            ->assertJsonPath('data.1.unlockedAt', null);

        $this->assertNotNull(
            $this->actingAs($this->user)->getJson('/api/v1/achievements')->json('data.0.unlockedAt'),
        );
    }

    public function test_a_missing_translation_falls_back_to_english(): void
    {
        $achievement = $this->achievement('first-journey', ['type' => 'journeys_completed', 'count' => 1]);
        $achievement->translations()->where('locale', 'id')->delete();

        $this->actingAs($this->user)->getJson('/api/v1/achievements?locale=id')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Title first-journey');
    }

    public function test_a_guest_cannot_list_achievements(): void
    {
        $this->getJson('/api/v1/achievements')->assertUnauthorized();
    }
}
