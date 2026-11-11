<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Content\ContentFixture;
use Tests\Feature\Learning\LearningFixture;
use Tests\TestCase;

class OverviewTest extends TestCase
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

    public function test_a_learner_cannot_read_the_overview(): void
    {
        $learner = User::factory()->create();
        $learner->assignRole('learner');

        $this->actingAs($learner)->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->actingAs($learner)->getJson('/api/v1/admin/tags')->assertForbidden();
    }

    public function test_it_counts_the_content_tree_and_names_the_journeys_with_no_quiz(): void
    {
        $track = LearningFixture::track(LearningFixture::roadmap(), 'indexing');
        $withQuiz = LearningFixture::journey($track, 'has-quiz', 1);
        LearningFixture::quiz($withQuiz, questionCount: 2);
        LearningFixture::journey($track, 'no-quiz', 2);
        LearningFixture::journey($track, 'also-no-quiz', 3, published: false);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.tracks.total', 1)
            ->assertJsonPath('data.tracks.published', 1)
            ->assertJsonPath('data.journeys.total', 3)
            ->assertJsonPath('data.journeys.published', 2)
            ->assertJsonPath('data.journeys.withoutQuiz', 2)
            ->assertJsonPath('data.quizzes.total', 1)
            ->assertJsonPath('data.questions.total', 2)
            ->assertJsonPath('data.questions.active', 2)
            ->assertJsonCount(2, 'data.emptyJourneys')
            ->assertJsonPath('data.emptyJourneys.0.slug', 'no-quiz')
            ->assertJsonPath('data.emptyJourneys.0.trackSlug', 'indexing')
            ->assertJsonPath('data.emptyJourneys.1.slug', 'also-no-quiz');
    }

    public function test_translation_coverage_counts_each_locale_against_the_question_total(): void
    {
        $this->seed(RoleSeeder::class);
        app(ContentSeeder::class)->sync(ContentFixture::minimal());

        $this->actingAs($this->admin)->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.translationCoverage.0.locale', 'en')
            ->assertJsonPath('data.translationCoverage.0.translated', 1)
            ->assertJsonPath('data.translationCoverage.0.total', 1)
            ->assertJsonPath('data.translationCoverage.1.locale', 'id')
            ->assertJsonPath('data.translationCoverage.1.translated', 0)
            ->assertJsonPath('data.translationCoverage.1.total', 1);
    }

    public function test_tracks_list_with_their_journeys_and_quizzes(): void
    {
        $track = LearningFixture::track(LearningFixture::roadmap(), 'indexing');
        $journey = LearningFixture::journey($track, 'has-quiz', 1);
        LearningFixture::quiz($journey, questionCount: 2);
        LearningFixture::journey($track, 'no-quiz', 2);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/tracks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'indexing')
            ->assertJsonPath('data.0.name', 'Track indexing')
            ->assertJsonPath('data.0.questionCount', 2)
            ->assertJsonPath('data.0.isPublished', true)
            ->assertJsonCount(2, 'data.0.journeys')
            ->assertJsonPath('data.0.journeys.0.slug', 'has-quiz')
            ->assertJsonPath('data.0.journeys.0.quizzes.0.slug', 'has-quiz-quiz')
            ->assertJsonPath('data.0.journeys.1.slug', 'no-quiz')
            ->assertJsonPath('data.0.journeys.1.quizzes', []);
    }

    public function test_the_tracks_list_does_not_grow_its_query_count_with_more_journeys(): void
    {
        $track = LearningFixture::track(LearningFixture::roadmap(), 'indexing');

        foreach (range(1, 3) as $i) {
            LearningFixture::journey($track, "j-{$i}", $i);
        }

        // One warm-up request first: the permission cache is loaded lazily and
        // would otherwise make the first measurement the expensive one.
        $this->actingAs($this->admin)->getJson('/api/v1/admin/tracks')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->getJson('/api/v1/admin/tracks')->assertJsonCount(3, 'data.0.journeys');
        $withThree = count(DB::getQueryLog());

        foreach (range(4, 14) as $i) {
            LearningFixture::journey($track, "j-{$i}", $i);
        }

        DB::flushQueryLog();
        $this->actingAs($this->admin)->getJson('/api/v1/admin/tracks')->assertJsonCount(14, 'data.0.journeys');
        $withFourteen = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withThree, $withFourteen, "query count grew from {$withThree} to {$withFourteen}");
    }

    public function test_tags_list_with_their_usage_and_can_be_created(): void
    {
        Tag::create(['slug' => 'redis', 'name' => 'Redis']);

        $this->actingAs($this->admin)->getJson('/api/v1/admin/tags')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'redis')
            ->assertJsonPath('data.0.questionCount', 0);

        $this->actingAs($this->admin)->postJson('/api/v1/admin/tags', ['slug' => 'wal', 'name' => 'WAL'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'wal');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/tags', ['slug' => 'wal', 'name' => 'WAL again'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($this->admin)->postJson('/api/v1/admin/tags', ['slug' => 'Not A Slug', 'name' => 'Nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }
}
