<?php

namespace Tests\Feature\Learning;

use App\Models\Roadmap;
use App\Models\Track;
use App\Models\User;
use App\Models\UserJourneyProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrackDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Roadmap $roadmap;

    private Track $track;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->roadmap = LearningFixture::roadmap();
        $this->track = LearningFixture::track($this->roadmap, 'indexing');
    }

    public function test_a_guest_cannot_read_a_track(): void
    {
        $this->getJson('/api/v1/tracks/indexing')->assertUnauthorized();
    }

    public function test_it_returns_published_journeys_in_position_order_with_their_quiz(): void
    {
        $first = LearningFixture::journey($this->track, 'b-tree', 1);
        LearningFixture::quiz($first, questionCount: 3);
        LearningFixture::journey($this->track, 'explain', 2, tier: 'intermediate');
        LearningFixture::journey($this->track, 'draft', 3, published: false);

        $this->actingAs($this->user)->getJson('/api/v1/tracks/indexing')
            ->assertOk()
            ->assertJsonCount(2, 'data.journeys')
            ->assertJsonPath('data.journeys.0.slug', 'b-tree')
            ->assertJsonPath('data.journeys.0.tier', 'foundation')
            ->assertJsonPath('data.journeys.0.quizSlug', 'b-tree-quiz')
            ->assertJsonPath('data.journeys.0.questionCount', 3)
            ->assertJsonPath('data.journeys.1.slug', 'explain')
            ->assertJsonPath('data.journeys.1.tier', 'intermediate')
            ->assertJsonPath('data.journeys.1.quizSlug', null);
    }

    public function test_the_first_journey_is_available_and_the_next_is_locked(): void
    {
        LearningFixture::journey($this->track, 'one', 1);
        LearningFixture::journey($this->track, 'two', 2);

        $this->actingAs($this->user)->getJson('/api/v1/tracks/indexing')
            ->assertOk()
            ->assertJsonPath('data.journeys.0.status', 'available')
            ->assertJsonPath('data.journeys.1.status', 'locked');
    }

    public function test_completing_a_journey_unlocks_the_next_one(): void
    {
        $one = LearningFixture::journey($this->track, 'one', 1);
        LearningFixture::journey($this->track, 'two', 2);
        LearningFixture::journey($this->track, 'three', 3);

        UserJourneyProgress::create([
            'user_id' => $this->user->id,
            'journey_id' => $one->id,
            'status' => 'completed',
            'best_score' => 90,
            'attempts_count' => 2,
        ]);

        $this->actingAs($this->user)->getJson('/api/v1/tracks/indexing')
            ->assertOk()
            ->assertJsonPath('data.journeys.0.status', 'completed')
            ->assertJsonPath('data.journeys.0.bestScore', 90)
            ->assertJsonPath('data.journeys.0.attemptsCount', 2)
            ->assertJsonPath('data.journeys.1.status', 'available')
            ->assertJsonPath('data.journeys.2.status', 'locked');
    }

    public function test_progress_belongs_to_the_signed_in_user_only(): void
    {
        $one = LearningFixture::journey($this->track, 'one', 1);
        LearningFixture::journey($this->track, 'two', 2);

        UserJourneyProgress::create([
            'user_id' => User::factory()->create()->id,
            'journey_id' => $one->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->user)->getJson('/api/v1/tracks/indexing')
            ->assertOk()
            ->assertJsonPath('data.journeys.0.status', 'available')
            ->assertJsonPath('data.journeys.1.status', 'locked');
    }

    public function test_an_unpublished_track_is_not_reachable(): void
    {
        $hidden = LearningFixture::track($this->roadmap, 'hidden', published: false, position: 2);
        LearningFixture::journey($hidden, 'one', 1);

        $this->actingAs($this->user)->getJson('/api/v1/tracks/hidden')->assertNotFound();
        $this->actingAs($this->user)->getJson('/api/v1/tracks/nope')->assertNotFound();
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_journeys(): void
    {
        foreach (range(1, 3) as $i) {
            LearningFixture::journey($this->track, "j{$i}", $i);
        }

        $this->actingAs($this->user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/tracks/indexing')->assertOk()->assertJsonCount(3, 'data.journeys');
        $withThree = count(DB::getQueryLog());

        foreach (range(4, 20) as $i) {
            LearningFixture::journey($this->track, "j{$i}", $i);
        }

        DB::flushQueryLog();
        $this->getJson('/api/v1/tracks/indexing')->assertOk()->assertJsonCount(20, 'data.journeys');
        $withTwenty = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame($withThree, $withTwenty, "query count grew from {$withThree} to {$withTwenty}");
    }
}
