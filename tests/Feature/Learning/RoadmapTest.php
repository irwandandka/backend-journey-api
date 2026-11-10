<?php

namespace Tests\Feature\Learning;

use App\Models\ReviewItem;
use App\Models\Roadmap;
use App\Models\User;
use App\Models\UserJourneyProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoadmapTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Roadmap $roadmap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->roadmap = LearningFixture::roadmap();
    }

    public function test_a_guest_cannot_read_the_roadmap(): void
    {
        $this->getJson('/api/v1/roadmap')->assertUnauthorized();
    }

    public function test_it_lists_published_tracks_with_journey_counts(): void
    {
        $indexing = LearningFixture::track($this->roadmap, 'indexing', position: 1);
        LearningFixture::journey($indexing, 'a', 1);
        LearningFixture::journey($indexing, 'b', 2);
        LearningFixture::journey($indexing, 'draft', 3, published: false);

        LearningFixture::track($this->roadmap, 'hidden', published: false, position: 2);

        $response = $this->actingAs($this->user)->getJson('/api/v1/roadmap')->assertOk();

        $response->assertJsonCount(1, 'data.tracks')
            ->assertJsonPath('data.tracks.0.slug', 'indexing')
            ->assertJsonPath('data.tracks.0.journeyCount', 2)
            ->assertJsonPath('data.tracks.0.completedCount', 0)
            ->assertJsonPath('data.tracks.0.dueCount', 0)
            ->assertJsonPath('data.tracks.0.colorDark', '#00E5FF')
            ->assertJsonPath('data.tracks.0.colorLight', '#0E7490');
    }

    public function test_it_counts_only_completed_journeys_for_the_signed_in_user(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        $first = LearningFixture::journey($track, 'a', 1);
        $second = LearningFixture::journey($track, 'b', 2);

        UserJourneyProgress::create([
            'user_id' => $this->user->id, 'journey_id' => $first->id, 'status' => 'completed',
        ]);
        UserJourneyProgress::create([
            'user_id' => $this->user->id, 'journey_id' => $second->id, 'status' => 'in_progress',
        ]);
        UserJourneyProgress::create([
            'user_id' => User::factory()->create()->id, 'journey_id' => $second->id, 'status' => 'completed',
        ]);

        $this->actingAs($this->user)->getJson('/api/v1/roadmap')
            ->assertOk()
            ->assertJsonPath('data.tracks.0.completedCount', 1);
    }

    public function test_the_due_count_ignores_items_that_are_not_due_yet(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        LearningFixture::journey($track, 'a', 1);

        ReviewItem::create([
            'user_id' => $this->user->id,
            'question_id' => LearningFixture::question($track, 'q-due')->id,
            'due_at' => now()->subHour(),
        ]);
        ReviewItem::create([
            'user_id' => $this->user->id,
            'question_id' => LearningFixture::question($track, 'q-later')->id,
            'due_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->user)->getJson('/api/v1/roadmap')
            ->assertOk()
            ->assertJsonPath('data.tracks.0.dueCount', 1);
    }

    public function test_it_serves_the_requested_locale(): void
    {
        LearningFixture::track($this->roadmap, 'indexing');

        $this->actingAs($this->user)->getJson('/api/v1/roadmap?locale=id')
            ->assertOk()
            ->assertHeader('Content-Language', 'id')
            ->assertJsonPath('data.title', 'Backend Indonesia')
            ->assertJsonPath('data.tracks.0.name', 'Jalur indexing');
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_tracks(): void
    {
        foreach (range(1, 3) as $i) {
            $track = LearningFixture::track($this->roadmap, "t{$i}", position: $i);
            LearningFixture::journey($track, "t{$i}-a", 1);
        }

        $this->actingAs($this->user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/roadmap')->assertOk()->assertJsonCount(3, 'data.tracks');
        $withThree = count(DB::getQueryLog());

        foreach (range(4, 12) as $i) {
            $track = LearningFixture::track($this->roadmap, "t{$i}", position: $i);
            LearningFixture::journey($track, "t{$i}-a", 1);
        }

        DB::flushQueryLog();
        $this->getJson('/api/v1/roadmap')->assertOk()->assertJsonCount(12, 'data.tracks');
        $withTwelve = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame($withThree, $withTwelve, "query count grew from {$withThree} to {$withTwelve}");
    }

    public function test_it_404s_when_no_roadmap_is_published(): void
    {
        Roadmap::query()->update(['is_published' => false]);

        $this->actingAs($this->user)->getJson('/api/v1/roadmap')->assertNotFound();
    }
}
