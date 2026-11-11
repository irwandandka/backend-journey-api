<?php

namespace Tests\Feature\Learning;

use App\Models\Roadmap;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JourneySearchTest extends TestCase
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

    public function test_a_guest_cannot_list_journeys(): void
    {
        $this->getJson('/api/v1/journeys')->assertUnauthorized();
    }

    public function test_it_returns_every_published_journey_flat_with_its_track(): void
    {
        $withQuiz = LearningFixture::journey($this->track, 'composite-index', 1);
        LearningFixture::quiz($withQuiz, questionCount: 2);
        LearningFixture::journey($this->track, 'reading-explain', 2, tier: 'advanced');

        $this->actingAs($this->user)->getJson('/api/v1/journeys')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'composite-index')
            ->assertJsonPath('data.0.trackSlug', 'indexing')
            ->assertJsonPath('data.0.trackName', 'Track indexing')
            ->assertJsonPath('data.0.tier', 'foundation')
            ->assertJsonPath('data.0.hasQuiz', true)
            ->assertJsonPath('data.1.slug', 'reading-explain')
            ->assertJsonPath('data.1.tier', 'advanced')
            ->assertJsonPath('data.1.hasQuiz', false);
    }

    public function test_it_hides_an_unpublished_journey_or_track(): void
    {
        LearningFixture::journey($this->track, 'visible', 1);
        LearningFixture::journey($this->track, 'draft-journey', 2, published: false);

        $hidden = LearningFixture::track($this->roadmap, 'hidden-track', published: false, position: 2);
        LearningFixture::journey($hidden, 'under-a-draft-track', 1);

        $this->actingAs($this->user)->getJson('/api/v1/journeys')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'visible');
    }

    public function test_it_serves_the_requested_locale_and_falls_back(): void
    {
        $journey = LearningFixture::journey($this->track, 'composite-index', 1);
        $journey->translations()->create(['locale' => 'id', 'title' => 'Index Komposit']);
        LearningFixture::journey($this->track, 'english-only', 2);

        $this->actingAs($this->user)->getJson('/api/v1/journeys?locale=id')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Index Komposit')
            ->assertJsonPath('data.0.trackName', 'Jalur indexing')
            ->assertJsonPath('data.1.title', 'Journey english-only');
    }

    public function test_it_does_not_grow_its_query_count_with_more_journeys(): void
    {
        foreach (range(1, 3) as $i) {
            LearningFixture::journey($this->track, "j-{$i}", $i);
        }

        $this->actingAs($this->user)->getJson('/api/v1/journeys')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->user)->getJson('/api/v1/journeys')->assertJsonCount(3, 'data');
        $withThree = count(DB::getQueryLog());

        foreach (range(4, 24) as $i) {
            LearningFixture::journey($this->track, "j-{$i}", $i);
        }

        DB::flushQueryLog();
        $this->actingAs($this->user)->getJson('/api/v1/journeys')->assertJsonCount(24, 'data');
        $withTwentyFour = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withThree, $withTwentyFour, "query count grew from {$withThree} to {$withTwentyFour}");
    }
}
