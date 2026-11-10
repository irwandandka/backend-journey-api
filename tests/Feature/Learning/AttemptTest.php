<?php

namespace Tests\Feature\Learning;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReviewItem;
use App\Models\User;
use App\Models\UserJourneyProgress;
use App\Models\UserXpEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $roadmap = LearningFixture::roadmap();
        $track = LearningFixture::track($roadmap, 'indexing');
        $journey = LearningFixture::journey($track, 'composite-index', 1);
        $this->quiz = LearningFixture::quiz($journey, questionCount: 2);
    }

    private function start(): int
    {
        return $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$this->quiz->slug}/attempts")
            ->assertCreated()
            ->json('data.id');
    }

    /** @return array{0: int, 1: int} position, correct option id */
    private function correctOption(int $attemptId, int $position): int
    {
        $served = QuizAttempt::find($attemptId)
            ->attemptQuestions()
            ->where('position', $position)
            ->with('questionVersion.options')
            ->sole();

        return (int) $served->questionVersion->options->firstWhere('is_correct', true)->id;
    }

    private function wrongOption(int $attemptId, int $position): int
    {
        $served = QuizAttempt::find($attemptId)
            ->attemptQuestions()
            ->where('position', $position)
            ->with('questionVersion.options')
            ->sole();

        return (int) $served->questionVersion->options->firstWhere('is_correct', false)->id;
    }

    public function test_a_guest_cannot_start_an_attempt(): void
    {
        $this->postJson("/api/v1/quizzes/{$this->quiz->slug}/attempts")->assertUnauthorized();
    }

    public function test_starting_creates_an_attempt_and_marks_the_journey_in_progress(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$this->quiz->slug}/attempts")
            ->assertCreated()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.questionCount', 2)
            ->assertJsonPath('data.answeredCount', 0)
            ->assertJsonPath('data.locale', 'en');

        $this->assertSame(1, QuizAttempt::count());
        $this->assertSame(2, QuizAttempt::find($response->json('data.id'))->attemptQuestions()->count());
        $this->assertSame('in_progress', UserJourneyProgress::sole()->status);
    }

    public function test_starting_again_resumes_the_same_attempt(): void
    {
        $first = $this->start();

        $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$this->quiz->slug}/attempts")
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertSame(1, QuizAttempt::count());
    }

    public function test_the_served_questions_never_leak_the_answer(): void
    {
        $attemptId = $this->start();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/attempts/{$attemptId}/questions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.answered', false)
            ->assertJsonCount(4, 'data.0.options');

        $body = $response->getContent();

        $this->assertStringContainsString('Stem for', $body);
        $this->assertStringNotContainsString('is_correct', $body);
        $this->assertStringNotContainsString('isCorrect', $body);
        $this->assertStringNotContainsString('correctOptionIds', $body);
        $this->assertStringNotContainsString('option_explanation', $body);
        $this->assertStringNotContainsString('Explanation for', $body);
        $this->assertStringNotContainsString('is right', $body);
        $this->assertStringNotContainsString('is wrong', $body);
    }

    public function test_a_correct_answer_returns_the_explanation_and_awards_xp(): void
    {
        $attemptId = $this->start();
        $optionId = $this->correctOption($attemptId, 0);

        $this->actingAs($this->user)
            ->postJson("/api/v1/attempts/{$attemptId}/answers", [
                'position' => 0,
                'optionIds' => [$optionId],
                'timeSpentMs' => 4200,
            ])
            ->assertOk()
            ->assertJsonPath('data.isCorrect', true)
            ->assertJsonPath('data.correctOptionIds', [$optionId])
            ->assertJsonPath('data.xpAwarded', 25)
            ->assertJsonPath('data.answeredCount', 1)
            ->assertJsonPath('data.questionCount', 2)
            ->assertJsonFragment(['explanation' => 'Explanation for q-composite-index-0']);

        $this->assertSame(1, QuizAttempt::find($attemptId)->correct_count);
    }

    public function test_a_wrong_answer_reveals_the_correct_option_and_awards_nothing(): void
    {
        $attemptId = $this->start();

        $this->actingAs($this->user)
            ->postJson("/api/v1/attempts/{$attemptId}/answers", [
                'position' => 0,
                'optionIds' => [$this->wrongOption($attemptId, 0)],
            ])
            ->assertOk()
            ->assertJsonPath('data.isCorrect', false)
            ->assertJsonPath('data.xpAwarded', 0)
            ->assertJsonPath('data.correctOptionIds', [$this->correctOption($attemptId, 0)]);

        $this->assertSame(0, QuizAttempt::find($attemptId)->correct_count);
    }

    public function test_a_question_cannot_be_answered_twice(): void
    {
        $attemptId = $this->start();
        $payload = ['position' => 0, 'optionIds' => [$this->correctOption($attemptId, 0)]];

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/answers", $payload)->assertOk();
        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/answers", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('position');
    }

    public function test_an_option_from_another_question_is_rejected(): void
    {
        $attemptId = $this->start();

        $this->actingAs($this->user)
            ->postJson("/api/v1/attempts/{$attemptId}/answers", [
                'position' => 0,
                'optionIds' => [$this->correctOption($attemptId, 1)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('optionIds');
    }

    public function test_a_position_outside_the_attempt_is_rejected(): void
    {
        $attemptId = $this->start();

        $this->actingAs($this->user)
            ->postJson("/api/v1/attempts/{$attemptId}/answers", ['position' => 9, 'optionIds' => [1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('position');
    }

    public function test_another_users_attempt_is_not_reachable(): void
    {
        $attemptId = $this->start();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->getJson("/api/v1/attempts/{$attemptId}/questions")->assertNotFound();
        $this->actingAs($intruder)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertNotFound();
    }

    public function test_submitting_with_unanswered_questions_is_refused(): void
    {
        $attemptId = $this->start();

        $this->actingAs($this->user)
            ->postJson("/api/v1/attempts/{$attemptId}/answers", [
                'position' => 0, 'optionIds' => [$this->correctOption($attemptId, 0)],
            ])->assertOk();

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/submit")
            ->assertStatus(422)
            ->assertJsonValidationErrors('attempt');
    }

    public function test_a_full_pass_scores_completes_the_journey_and_grants_xp(): void
    {
        $attemptId = $this->start();

        foreach ([0, 1] as $position) {
            $this->actingAs($this->user)
                ->postJson("/api/v1/attempts/{$attemptId}/answers", [
                    'position' => $position,
                    'optionIds' => [$this->correctOption($attemptId, $position)],
                ])->assertOk();
        }

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.correctCount', 2)
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.xpEarned', 50)
            ->assertJsonPath('data.xpTotal', 50)
            ->assertJsonPath('data.currentStreak', 1)
            ->assertJsonPath('data.journeyStatus', 'completed')
            ->assertJsonPath('data.scheduledForReview', []);

        $this->assertSame('submitted', QuizAttempt::find($attemptId)->status);
        $this->assertSame(50, $this->user->fresh()->xp_total);
        $this->assertSame(50, UserXpEvent::sole()->amount);
        $this->assertSame(0, ReviewItem::count());

        $progress = UserJourneyProgress::sole();
        $this->assertSame('completed', $progress->status);
        $this->assertSame(100, $progress->best_score);
        $this->assertSame(1, $progress->attempts_count);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_a_missed_question_is_scheduled_for_tomorrow_and_the_journey_stays_open(): void
    {
        $attemptId = $this->start();

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/answers", [
            'position' => 0, 'optionIds' => [$this->correctOption($attemptId, 0)],
        ])->assertOk();

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/answers", [
            'position' => 1, 'optionIds' => [$this->wrongOption($attemptId, 1)],
        ])->assertOk();

        $response = $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('data.score', 50)
            ->assertJsonPath('data.passed', false)
            ->assertJsonPath('data.xpEarned', 25)
            ->assertJsonPath('data.journeyStatus', 'in_progress')
            ->assertJsonCount(1, 'data.scheduledForReview');

        $this->assertSame(1, ReviewItem::count());

        $item = ReviewItem::sole();
        $this->assertSame(1, $item->interval_days);
        $this->assertSame(1, $item->lapses);
        $this->assertTrue($item->due_at->isTomorrow());
        $this->assertSame(1, $response->json('data.scheduledForReview.0.position'));
        $this->assertSame('Stem for q-composite-index-1', $response->json('data.scheduledForReview.0.stem'));
    }

    public function test_an_attempt_cannot_be_submitted_twice(): void
    {
        $attemptId = $this->start();

        foreach ([0, 1] as $position) {
            $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/answers", [
                'position' => $position, 'optionIds' => [$this->correctOption($attemptId, $position)],
            ])->assertOk();
        }

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();
        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/submit")
            ->assertStatus(422)
            ->assertJsonValidationErrors('attempt');
    }

    public function test_answering_a_submitted_attempt_is_refused(): void
    {
        $attemptId = $this->start();

        foreach ([0, 1] as $position) {
            $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/answers", [
                'position' => $position, 'optionIds' => [$this->correctOption($attemptId, $position)],
            ])->assertOk();
        }

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        $this->actingAs($this->user)->postJson("/api/v1/attempts/{$attemptId}/answers", [
            'position' => 0, 'optionIds' => [$this->correctOption($attemptId, 0)],
        ])->assertStatus(422)->assertJsonValidationErrors('attempt');
    }
}
