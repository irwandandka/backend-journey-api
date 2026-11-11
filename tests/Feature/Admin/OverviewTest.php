<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->seed(\Database\Seeders\RoleSeeder::class);
        app(\Database\Seeders\ContentSeeder::class)->sync(ContentFixture::minimal());

        $this->actingAs($this->admin)->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.translationCoverage.0.locale', 'en')
            ->assertJsonPath('data.translationCoverage.0.translated', 1)
            ->assertJsonPath('data.translationCoverage.0.total', 1)
            ->assertJsonPath('data.translationCoverage.1.locale', 'id')
            ->assertJsonPath('data.translationCoverage.1.translated', 0)
            ->assertJsonPath('data.translationCoverage.1.total', 1);
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
