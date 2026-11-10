<?php

namespace Tests\Feature\Learning;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Track;
use App\Models\User;
use App\Models\UserJourneyProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockedJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Track $track;

    private Quiz $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $roadmap = LearningFixture::roadmap();
        $this->track = LearningFixture::track($roadmap, 'indexing');

        $first = LearningFixture::journey($this->track, 'one', 1);
        LearningFixture::quiz($first, questionCount: 1);

        $secondJourney = LearningFixture::journey($this->track, 'two', 2);
        $this->second = LearningFixture::quiz($secondJourney, questionCount: 1);
    }

    public function test_a_locked_journey_refuses_a_new_attempt(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$this->second->slug}/attempts")
            ->assertStatus(422)
            ->assertJsonValidationErrors('journey');

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_the_first_journey_of_a_track_is_always_startable(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/quizzes/one-quiz/attempts')
            ->assertCreated();
    }

    public function test_completing_the_previous_journey_opens_the_next(): void
    {
        UserJourneyProgress::create([
            'user_id' => $this->user->id,
            'journey_id' => $this->track->journeys()->where('slug', 'one')->sole()->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$this->second->slug}/attempts")
            ->assertCreated();
    }

    public function test_an_open_rule_ignores_the_order(): void
    {
        $free = LearningFixture::journey($this->track, 'free', 3, rule: 'open');
        $quiz = LearningFixture::quiz($free, questionCount: 1);

        $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$quiz->slug}/attempts")
            ->assertCreated();
    }

    public function test_an_attempt_already_in_progress_can_still_be_resumed(): void
    {
        $journeyId = $this->second->journey_id;

        UserJourneyProgress::create([
            'user_id' => $this->user->id,
            'journey_id' => $this->track->journeys()->where('slug', 'one')->sole()->id,
            'status' => 'completed',
        ]);

        $id = $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$this->second->slug}/attempts")
            ->assertCreated()
            ->json('data.id');

        UserJourneyProgress::where('journey_id', $journeyId)->delete();
        UserJourneyProgress::where('user_id', $this->user->id)->update(['status' => 'available']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$this->second->slug}/attempts")
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }
}
