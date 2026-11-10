<?php

namespace Tests\Feature\Learning;

use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\ReviewItem;
use App\Models\Track;
use App\Models\User;
use App\Models\UserJourneyProgress;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Track $track;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->track = LearningFixture::track(LearningFixture::roadmap(), 'indexing');
    }

    private function dueQuestion(string $key, string $due = '-1 hour', string $difficulty = 'medium'): Question
    {
        $question = LearningFixture::question($this->track, $key, $difficulty);

        ReviewItem::create([
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'due_at' => now()->modify($due),
            'interval_days' => 1,
            'lapses' => 1,
        ]);

        return $question;
    }

    private function correctOption(int $attemptId, int $position): int
    {
        return (int) QuizAttempt::find($attemptId)
            ->attemptQuestions()
            ->where('position', $position)
            ->with('questionVersion.options')
            ->sole()
            ->questionVersion->options->firstWhere('is_correct', true)->id;
    }

    private function wrongOption(int $attemptId, int $position): int
    {
        return (int) QuizAttempt::find($attemptId)
            ->attemptQuestions()
            ->where('position', $position)
            ->with('questionVersion.options')
            ->sole()
            ->questionVersion->options->firstWhere('is_correct', false)->id;
    }

    public function test_a_guest_cannot_read_the_review_summary(): void
    {
        $this->getJson('/api/v1/review')->assertUnauthorized();
    }

    public function test_the_summary_separates_due_from_merely_tracked(): void
    {
        $this->dueQuestion('q-a', '-2 hours');
        $this->dueQuestion('q-b', '-1 hour');
        $this->dueQuestion('q-c', '+3 days');

        $this->actingAs($this->user)->getJson('/api/v1/review')
            ->assertOk()
            ->assertJsonPath('data.dueCount', 2)
            ->assertJsonPath('data.trackedCount', 3)
            ->assertJsonPath('data.sessionSize', AttemptService::REVIEW_SESSION_SIZE)
            ->assertJsonPath('data.nextDueAt', now()->addDays(3)->toIso8601String());
    }

    public function test_the_summary_is_empty_for_a_user_with_no_history(): void
    {
        $this->actingAs($this->user)->getJson('/api/v1/review')
            ->assertOk()
            ->assertJsonPath('data.dueCount', 0)
            ->assertJsonPath('data.trackedCount', 0)
            ->assertJsonPath('data.nextDueAt', null);
    }

    public function test_starting_a_session_with_nothing_due_is_refused(): void
    {
        $this->dueQuestion('q-later', '+2 days');

        $this->actingAs($this->user)->postJson('/api/v1/review/attempts')
            ->assertStatus(422)
            ->assertJsonValidationErrors('review');

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_a_session_takes_the_due_questions_oldest_first_and_is_not_tied_to_a_quiz(): void
    {
        $this->dueQuestion('q-newest', '-1 minute');
        $this->dueQuestion('q-oldest', '-5 days');
        $this->dueQuestion('q-middle', '-2 hours');
        $this->dueQuestion('q-future', '+1 day');

        $id = $this->actingAs($this->user)->postJson('/api/v1/review/attempts')
            ->assertCreated()
            ->assertJsonPath('data.kind', 'review')
            ->assertJsonPath('data.quizSlug', null)
            ->assertJsonPath('data.questionCount', 3)
            ->json('data.id');

        $keys = QuizAttempt::find($id)->attemptQuestions()
            ->orderBy('position')
            ->pluck('snapshot')
            ->map(fn ($snapshot) => $snapshot['question_key'])
            ->all();

        $this->assertSame(['q-oldest', 'q-middle', 'q-newest'], $keys);
    }

    public function test_a_session_is_capped_at_the_session_size(): void
    {
        foreach (range(1, AttemptService::REVIEW_SESSION_SIZE + 4) as $i) {
            $this->dueQuestion("q-{$i}", '-'.$i.' hours');
        }

        $this->actingAs($this->user)->postJson('/api/v1/review/attempts')
            ->assertCreated()
            ->assertJsonPath('data.questionCount', AttemptService::REVIEW_SESSION_SIZE);
    }

    public function test_only_one_review_session_runs_at_a_time(): void
    {
        $this->dueQuestion('q-a');

        $first = $this->actingAs($this->user)->postJson('/api/v1/review/attempts')->assertCreated()->json('data.id');

        $this->actingAs($this->user)->postJson('/api/v1/review/attempts')
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_the_served_review_questions_leak_nothing(): void
    {
        $this->dueQuestion('q-a');

        $id = $this->actingAs($this->user)->postJson('/api/v1/review/attempts')->json('data.id');
        $body = $this->actingAs($this->user)->getJson("/api/v1/attempts/{$id}/questions")->assertOk()->getContent();

        $this->assertStringNotContainsString('is_correct', $body);
        $this->assertStringNotContainsString('isCorrect', $body);
        $this->assertStringNotContainsString('Explanation for', $body);
    }

    public function test_clearing_a_review_pushes_the_due_date_out_and_leaves_journeys_alone(): void
    {
        $this->dueQuestion('q-a', '-1 hour', 'hard');

        $id = $this->actingAs($this->user)->postJson('/api/v1/review/attempts')->json('data.id');

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$id}/answers", [
            'position' => 0, 'optionIds' => [$this->correctOption($id, 0)],
        ])->assertOk()->assertJsonPath('data.xpAwarded', 40);

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.journeyStatus', null)
            ->assertJsonPath('data.xpEarned', 40)
            ->assertJsonPath('data.currentStreak', 1)
            ->assertJsonPath('data.scheduledForReview', []);

        $item = ReviewItem::sole();
        $this->assertSame(1, $item->repetitions);
        $this->assertSame(3, $item->interval_days, 'a first clean review moves the card to day 3');
        $this->assertSame(2.55, $item->ease_factor);
        $this->assertSame(1, $item->lapses, 'the lapse history is kept');
        $this->assertTrue($item->due_at->isFuture());
        $this->assertSame(0, UserJourneyProgress::count());
        $this->assertSame(40, $this->user->fresh()->xp_total);
    }

    public function test_missing_a_review_question_again_schedules_it_for_tomorrow(): void
    {
        $this->dueQuestion('q-a');

        $id = $this->actingAs($this->user)->postJson('/api/v1/review/attempts')->json('data.id');

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$id}/answers", [
            'position' => 0, 'optionIds' => [$this->wrongOption($id, 0)],
        ])->assertOk();

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.score', 0)
            ->assertJsonPath('data.passed', false)
            ->assertJsonCount(1, 'data.scheduledForReview');

        $item = ReviewItem::sole();
        $this->assertSame(2, $item->lapses);
        $this->assertSame(1, $item->interval_days);
        $this->assertTrue($item->due_at->isTomorrow());
    }

    public function test_a_review_session_does_not_block_a_normal_quiz_attempt(): void
    {
        $journey = LearningFixture::journey($this->track, 'one', 1);
        $quiz = LearningFixture::quiz($journey, questionCount: 1);
        $this->dueQuestion('q-a');

        $this->actingAs($this->user)->postJson('/api/v1/review/attempts')->assertCreated();
        $this->actingAs($this->user)->postJson("/api/v1/quizzes/{$quiz->slug}/attempts")->assertCreated();

        $this->assertSame(2, QuizAttempt::count());
    }
}
