<?php

namespace Tests\Feature\Admin;

use App\Models\Roadmap;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Learning\LearningFixture;
use Tests\TestCase;

class RoadmapEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'slug' => 'frontend-engineering',
            'position' => 2,
            'isPublished' => false,
            'translations' => [
                'en' => ['title' => 'Frontend Engineering', 'description' => 'The other half.'],
                'id' => ['title' => 'Frontend Engineering', 'description' => 'Setengah lainnya.'],
            ],
        ], $overrides);
    }

    public function test_a_learner_cannot_touch_roadmaps(): void
    {
        $learner = User::factory()->create();
        $learner->assignRole('learner');

        $this->actingAs($learner)->getJson('/api/v1/admin/roadmaps')->assertForbidden();
        $this->actingAs($learner)->postJson('/api/v1/admin/roadmaps', $this->payload())->assertForbidden();
    }

    public function test_it_lists_roadmaps_with_their_track_count(): void
    {
        $roadmap = LearningFixture::roadmap();
        LearningFixture::track($roadmap, 'indexing');
        LearningFixture::track($roadmap, 'caching', position: 2);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/roadmaps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'backend')
            ->assertJsonPath('data.0.title', 'Backend')
            ->assertJsonPath('data.0.trackCount', 2)
            ->assertJsonPath('data.0.isPublished', true);
    }

    public function test_it_creates_and_updates_a_roadmap(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/roadmaps', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'frontend-engineering')
            ->assertJsonPath('data.title', 'Frontend Engineering')
            ->assertJsonPath('data.isPublished', false)
            ->assertJsonPath('data.trackCount', 0);

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/roadmaps/frontend-engineering', $this->payload(['isPublished' => true, 'position' => 5]))
            ->assertOk()
            ->assertJsonPath('data.isPublished', true)
            ->assertJsonPath('data.position', 5);

        $this->assertSame(1, Roadmap::where('slug', 'frontend-engineering')->count());
    }

    public function test_a_duplicate_slug_a_rename_and_a_missing_fallback_are_refused(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/roadmaps', $this->payload())->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/v1/admin/roadmaps', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/roadmaps/frontend-engineering', $this->payload(['slug' => 'renamed']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $payload = $this->payload(['slug' => 'another']);
        unset($payload['translations']['en']);

        $this->actingAs($this->admin)->postJson('/api/v1/admin/roadmaps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('translations');
    }

    public function test_a_track_can_be_attached_to_a_chosen_roadmap_and_moved_between_them(): void
    {
        $first = LearningFixture::roadmap();
        $this->actingAs($this->admin)->postJson('/api/v1/admin/roadmaps', $this->payload())->assertCreated();

        $trackPayload = [
            'slug' => 'components',
            'abbreviation' => 'CO',
            'colorDark' => '#00e5ff',
            'colorLight' => '#0e7490',
            'position' => 1,
            'isPublished' => true,
            'roadmapSlug' => 'frontend-engineering',
            'translations' => ['en' => ['name' => 'Components', 'description' => null]],
        ];

        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $trackPayload)->assertCreated();

        $this->assertSame(
            'frontend-engineering',
            Track::where('slug', 'components')->sole()->roadmap->slug,
        );

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/tracks/components', [...$trackPayload, 'roadmapSlug' => $first->slug])
            ->assertOk();

        $this->assertSame($first->slug, Track::where('slug', 'components')->sole()->roadmap->slug);
    }

    public function test_a_track_with_no_roadmap_named_lands_on_the_first_one(): void
    {
        $first = LearningFixture::roadmap();
        $this->actingAs($this->admin)->postJson('/api/v1/admin/roadmaps', $this->payload())->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', [
            'slug' => 'observability',
            'abbreviation' => 'OB',
            'colorDark' => '#ff4fd8',
            'colorLight' => '#c026d3',
            'position' => 1,
            'isPublished' => true,
            'translations' => ['en' => ['name' => 'Observability', 'description' => null]],
        ])->assertCreated();

        $this->assertSame($first->slug, Track::where('slug', 'observability')->sole()->roadmap->slug);
    }

    public function test_an_unknown_roadmap_slug_is_refused(): void
    {
        LearningFixture::roadmap();

        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', [
            'slug' => 'observability',
            'abbreviation' => 'OB',
            'colorDark' => '#ff4fd8',
            'colorLight' => '#c026d3',
            'position' => 1,
            'isPublished' => true,
            'roadmapSlug' => 'no-such-roadmap',
            'translations' => ['en' => ['name' => 'Observability', 'description' => null]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roadmapSlug');
    }
}
