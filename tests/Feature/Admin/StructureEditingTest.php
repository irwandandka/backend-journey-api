<?php

namespace Tests\Feature\Admin;

use App\Models\Journey;
use App\Models\Quiz;
use App\Models\Roadmap;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Learning\LearningFixture;
use Tests\TestCase;

class StructureEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Roadmap $roadmap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->roadmap = LearningFixture::roadmap();
    }

    /** @return array<string, mixed> */
    private function trackPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'slug' => 'observability',
            'abbreviation' => 'ob',
            'colorDark' => '#FF4FD8',
            'colorLight' => '#C026D3',
            'position' => 6,
            'isPublished' => true,
            'translations' => [
                'en' => ['name' => 'Observability', 'description' => 'Knowing what production is doing.'],
                'id' => ['name' => 'Observability', 'description' => 'Tahu apa yang sedang terjadi di produksi.'],
            ],
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function journeyPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'slug' => 'structured-logging',
            'trackSlug' => 'observability',
            'tier' => 'foundation',
            'unlockRule' => 'sequential',
            'position' => 1,
            'passThreshold' => 70,
            'isPublished' => true,
            'translations' => ['en' => ['title' => 'Structured Logging', 'description' => null]],
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function quizPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'slug' => 'structured-logging-core',
            'journeySlug' => 'structured-logging',
            'questionCount' => 2,
            'timeLimitS' => null,
            'shuffleQuestions' => true,
            'shuffleOptions' => true,
            'position' => 0,
            'translations' => ['en' => ['title' => 'Structured Logging']],
        ], $overrides);
    }

    public function test_a_learner_cannot_edit_the_structure(): void
    {
        $learner = User::factory()->create();
        $learner->assignRole('learner');

        $this->actingAs($learner)->postJson('/api/v1/admin/tracks', $this->trackPayload())->assertForbidden();
        $this->actingAs($learner)->postJson('/api/v1/admin/journeys', $this->journeyPayload())->assertForbidden();
        $this->actingAs($learner)->postJson('/api/v1/admin/quizzes', $this->quizPayload())->assertForbidden();
    }

    public function test_it_creates_a_track_a_journey_and_a_quiz_in_sequence(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $this->trackPayload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'observability')
            ->assertJsonPath('data.abbreviation', 'OB')
            ->assertJsonPath('data.colorDark', '#ff4fd8')
            ->assertJsonPath('data.name', 'Observability')
            ->assertJsonPath('data.journeys', []);

        $this->actingAs($this->admin)->postJson('/api/v1/admin/journeys', $this->journeyPayload())
            ->assertCreated()
            ->assertJsonCount(1, 'data.journeys')
            ->assertJsonPath('data.journeys.0.slug', 'structured-logging')
            ->assertJsonPath('data.journeys.0.title', 'Structured Logging')
            ->assertJsonPath('data.journeys.0.passThreshold', 70)
            ->assertJsonPath('data.journeys.0.quizzes', []);

        $this->actingAs($this->admin)->postJson('/api/v1/admin/quizzes', $this->quizPayload())
            ->assertCreated()
            ->assertJsonPath('data.journeys.0.quizzes.0.slug', 'structured-logging-core')
            ->assertJsonPath('data.journeys.0.quizzes.0.questionCount', 2);

        $this->assertSame(1, Track::where('slug', 'observability')->count());
        $this->assertSame(1, Journey::where('slug', 'structured-logging')->count());
        $this->assertSame(1, Quiz::where('slug', 'structured-logging-core')->count());
    }

    public function test_a_duplicate_slug_is_refused_at_every_level(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $this->trackPayload())->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $this->trackPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/journeys', $this->journeyPayload())->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/admin/journeys', $this->journeyPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/quizzes', $this->quizPayload())->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/admin/quizzes', $this->quizPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_a_slug_cannot_be_renamed(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $this->trackPayload())->assertCreated();

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/tracks/observability', $this->trackPayload(['slug' => 'renamed']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_the_fallback_locale_is_required_on_every_structure_write(): void
    {
        $payload = $this->trackPayload();
        unset($payload['translations']['en']);

        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('translations');

        $this->assertSame(0, Track::count());
    }

    public function test_clearing_a_translation_removes_the_row(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $this->trackPayload())->assertCreated();

        $track = Track::where('slug', 'observability')->sole();
        $this->assertSame(2, $track->translations()->count());

        $payload = $this->trackPayload();
        unset($payload['translations']['id']);

        $this->actingAs($this->admin)->putJson('/api/v1/admin/tracks/observability', $payload)->assertOk();

        $this->assertSame(['en'], $track->translations()->pluck('locale')->all());
    }

    public function test_a_bad_colour_or_tier_or_rule_is_refused(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $this->trackPayload(['colorDark' => 'hotpink']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('colorDark');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $this->trackPayload())->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/v1/admin/journeys', $this->journeyPayload(['tier' => 'expert']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tier');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/journeys', $this->journeyPayload(['unlockRule' => 'whenever']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('unlockRule');
    }

    public function test_a_quiz_cannot_serve_more_questions_than_it_has(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing', position: 2);
        $journey = LearningFixture::journey($track, 'composite-index', 1);
        $quiz = LearningFixture::quiz($journey, questionCount: 2);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/quizzes/{$quiz->slug}", [
                'slug' => $quiz->slug,
                'journeySlug' => $journey->slug,
                'questionCount' => 5,
                'timeLimitS' => null,
                'shuffleQuestions' => false,
                'shuffleOptions' => false,
                'position' => 0,
                'translations' => ['en' => ['title' => 'Composite index']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('questionCount');
    }

    public function test_quiz_questions_are_set_in_the_order_they_are_sent(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing', position: 2);
        $journey = LearningFixture::journey($track, 'composite-index', 1);
        $quiz = LearningFixture::quiz($journey, questionCount: 2);
        $extra = LearningFixture::question($track, 'q-extra');

        $this->actingAs($this->admin)->getJson("/api/v1/admin/quizzes/{$quiz->slug}/questions")
            ->assertOk()
            ->assertJsonPath('data.keys', ['q-composite-index-0', 'q-composite-index-1']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/quizzes/{$quiz->slug}/questions", [
                'keys' => [$extra->key, 'q-composite-index-1', 'q-composite-index-0'],
            ])
            ->assertOk()
            ->assertJsonPath('data.keys', ['q-extra', 'q-composite-index-1', 'q-composite-index-0']);
    }

    public function test_a_quiz_cannot_be_left_with_fewer_questions_than_it_serves(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing', position: 2);
        $journey = LearningFixture::journey($track, 'composite-index', 1);
        $quiz = LearningFixture::quiz($journey, questionCount: 2);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/quizzes/{$quiz->slug}/questions", ['keys' => ['q-composite-index-0']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('keys');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/quizzes/{$quiz->slug}/questions", ['keys' => ['q-nope']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('keys.0');
    }

    public function test_detaching_every_question_is_allowed_so_a_quiz_can_be_rebuilt(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing', position: 2);
        $journey = LearningFixture::journey($track, 'composite-index', 1);
        $quiz = LearningFixture::quiz($journey, questionCount: 2);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/quizzes/{$quiz->slug}/questions", ['keys' => []])
            ->assertOk()
            ->assertJsonPath('data.keys', []);
    }

    public function test_an_unpublished_journey_is_invisible_to_a_learner_until_it_is_published(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/tracks', $this->trackPayload())->assertCreated();
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/journeys', $this->journeyPayload(['isPublished' => false]))
            ->assertCreated();

        $learner = User::factory()->create();
        $learner->assignRole('learner');

        $this->actingAs($learner)->getJson('/api/v1/tracks/observability')
            ->assertOk()
            ->assertJsonCount(0, 'data.journeys');

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/journeys/structured-logging', $this->journeyPayload(['isPublished' => true]))
            ->assertOk();

        $this->actingAs($learner)->getJson('/api/v1/tracks/observability')
            ->assertOk()
            ->assertJsonCount(1, 'data.journeys')
            ->assertJsonPath('data.journeys.0.slug', 'structured-logging');
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->actingAs($this->admin)->putJson('/api/v1/admin/tracks/nope', $this->trackPayload(['slug' => 'nope']))->assertNotFound();
        $this->actingAs($this->admin)->getJson('/api/v1/admin/quizzes/nope/questions')->assertNotFound();
    }
}
