<?php

namespace Tests\Feature\Admin;

use App\Models\Journey;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\Quiz;
use App\Models\Roadmap;
use App\Models\Tag;
use App\Models\Track;
use App\Models\User;
use App\Models\UserJourneyProgress;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Learning\LearningFixture;
use Tests\TestCase;

class DeletionTest extends TestCase
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

    public function test_a_learner_cannot_delete_anything(): void
    {
        $learner = User::factory()->create();
        $learner->assignRole('learner');

        $track = LearningFixture::track($this->roadmap, 'indexing');

        $this->actingAs($learner)->deleteJson("/api/v1/admin/tracks/{$track->slug}")->assertForbidden();
        $this->actingAs($learner)->deleteJson('/api/v1/admin/roadmaps/backend')->assertForbidden();
    }

    public function test_an_empty_track_can_be_deleted(): void
    {
        $track = LearningFixture::track($this->roadmap, 'empty-track');

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/tracks/{$track->slug}")->assertNoContent();

        $this->assertSame(0, Track::where('slug', 'empty-track')->count());
    }

    public function test_a_track_with_journeys_or_questions_is_refused(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        LearningFixture::journey($track, 'composite-index', 1);

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/tracks/{$track->slug}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('track');

        $this->assertSame(1, Track::where('slug', 'indexing')->count());

        $bare = LearningFixture::track($this->roadmap, 'bare', position: 2);
        LearningFixture::question($bare, 'q-orphan');

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/tracks/{$bare->slug}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('track');
    }

    public function test_a_journey_with_a_quiz_or_learner_progress_is_refused(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        $withQuiz = LearningFixture::journey($track, 'has-quiz', 1);
        LearningFixture::quiz($withQuiz, questionCount: 2);

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/journeys/{$withQuiz->slug}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('journey');

        $touched = LearningFixture::journey($track, 'touched', 2);
        UserJourneyProgress::create([
            'user_id' => $this->admin->id,
            'journey_id' => $touched->id,
            'status' => 'in_progress',
            'attempts_count' => 1,
        ]);

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/journeys/{$touched->slug}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('journey');

        $this->assertSame(2, Journey::count());
    }

    public function test_an_untouched_journey_can_be_deleted(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        $journey = LearningFixture::journey($track, 'draft-journey', 1);

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/journeys/{$journey->slug}")->assertNoContent();

        $this->assertSame(0, Journey::where('slug', 'draft-journey')->count());
    }

    public function test_a_quiz_with_attempts_is_refused_and_an_untouched_one_is_detached_and_removed(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        $journey = LearningFixture::journey($track, 'composite-index', 1, rule: 'open');
        $quiz = LearningFixture::quiz($journey, questionCount: 2);

        $learner = User::factory()->create();
        $this->actingAs($learner)->postJson("/api/v1/quizzes/{$quiz->slug}/attempts")->assertCreated();

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/quizzes/{$quiz->slug}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('quiz');

        $second = LearningFixture::journey($track, 'untouched', 2, rule: 'open');
        $fresh = LearningFixture::quiz($second, questionCount: 2);
        $attached = $fresh->questions()->count();

        $this->assertSame(2, $attached);

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/quizzes/{$fresh->slug}")->assertNoContent();

        $this->assertSame(0, Quiz::where('slug', $fresh->slug)->count());
        $this->assertSame(0, DB::table('quiz_questions')->where('quiz_id', $fresh->id)->count());
    }

    public function test_a_served_question_cannot_be_deleted(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        $journey = LearningFixture::journey($track, 'composite-index', 1, rule: 'open');
        $quiz = LearningFixture::quiz($journey, questionCount: 2);

        $learner = User::factory()->create();
        $this->actingAs($learner)->postJson("/api/v1/quizzes/{$quiz->slug}/attempts")->assertCreated();

        $key = $quiz->questions()->first()->key;

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/questions/{$key}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');

        $this->assertSame(1, Question::where('key', $key)->count());
    }

    public function test_a_question_still_in_a_quiz_is_refused(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        $quiz = LearningFixture::quiz(LearningFixture::journey($track, 'composite-index', 1), questionCount: 2);
        $key = $quiz->questions()->first()->key;

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/questions/{$key}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_an_unused_question_is_deleted_with_its_versions(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        $question = LearningFixture::question($track, 'q-orphan');

        $this->assertSame(1, QuestionVersion::where('question_id', $question->id)->count());

        $this->actingAs($this->admin)->deleteJson('/api/v1/admin/questions/q-orphan')->assertNoContent();

        $this->assertSame(0, Question::where('key', 'q-orphan')->count());
        $this->assertSame(0, QuestionVersion::where('question_id', $question->id)->count());
    }

    public function test_a_tag_in_use_is_refused_and_an_unused_one_is_deleted(): void
    {
        $track = LearningFixture::track($this->roadmap, 'indexing');
        $question = LearningFixture::question($track, 'q-tagged');
        $used = Tag::create(['slug' => 'postgres', 'name' => 'PostgreSQL']);
        $question->tags()->sync([$used->id]);

        Tag::create(['slug' => 'unused', 'name' => 'Unused']);

        $this->actingAs($this->admin)->deleteJson('/api/v1/admin/tags/postgres')
            ->assertStatus(422)
            ->assertJsonValidationErrors('tag');

        $this->actingAs($this->admin)->deleteJson('/api/v1/admin/tags/unused')->assertNoContent();

        $this->assertSame(['postgres'], Tag::pluck('slug')->all());
    }

    public function test_a_roadmap_with_tracks_is_refused(): void
    {
        LearningFixture::track($this->roadmap, 'indexing');

        $this->actingAs($this->admin)->deleteJson("/api/v1/admin/roadmaps/{$this->roadmap->slug}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('roadmap');

        $this->assertSame(1, Roadmap::count());
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->actingAs($this->admin)->deleteJson('/api/v1/admin/tracks/nope')->assertNotFound();
        $this->actingAs($this->admin)->deleteJson('/api/v1/admin/questions/q-nope')->assertNotFound();
        $this->actingAs($this->admin)->deleteJson('/api/v1/admin/tags/nope')->assertNotFound();
    }
}
