<?php

namespace Tests\Feature\Learning;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReviewItem;
use App\Models\Tag;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsightTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Track $indexing;

    private Track $caching;

    private Quiz $indexQuiz;

    private Quiz $cacheQuiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);

        $roadmap = LearningFixture::roadmap();
        $this->indexing = LearningFixture::track($roadmap, 'indexing');
        $this->caching = LearningFixture::track($roadmap, 'caching', position: 2);

        $this->indexQuiz = LearningFixture::quiz(
            LearningFixture::journey($this->indexing, 'composite-index', 1, rule: 'open'),
            questionCount: 2,
        );
        $this->cacheQuiz = LearningFixture::quiz(
            LearningFixture::journey($this->caching, 'stampede', 1, rule: 'open'),
            questionCount: 2,
        );

        $postgres = Tag::create(['slug' => 'postgres', 'name' => 'PostgreSQL']);
        $redis = Tag::create(['slug' => 'redis', 'name' => 'Redis']);

        foreach (Question::where('track_id', $this->indexing->id)->get() as $question) {
            $question->tags()->sync([$postgres->id]);
        }

        foreach (Question::where('track_id', $this->caching->id)->get() as $question) {
            $question->tags()->sync([$redis->id]);
        }
    }

    private function play(Quiz $quiz, bool ...$correct): void
    {
        $attemptId = $this->actingAs($this->user)
            ->postJson("/api/v1/quizzes/{$quiz->slug}/attempts")
            ->assertSuccessful()
            ->json('data.id');

        $served = QuizAttempt::find($attemptId)->attemptQuestions()->with('questionVersion.options')->get();

        foreach ($served as $index => $row) {
            $wantCorrect = $correct[$index] ?? false;
            $option = $row->questionVersion->options->firstWhere('is_correct', $wantCorrect);

            $this->postJson("/api/v1/attempts/{$attemptId}/answers", [
                'position' => $row->position,
                'optionIds' => [$option->id],
            ])->assertOk();
        }

        $this->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();
    }

    public function test_a_guest_cannot_read_insights(): void
    {
        $this->getJson('/api/v1/insights')->assertUnauthorized();
    }

    public function test_a_user_with_no_history_gets_empty_insights(): void
    {
        $this->actingAs($this->user)->getJson('/api/v1/insights')
            ->assertOk()
            ->assertJsonPath('data.overall.answered', 0)
            ->assertJsonPath('data.overall.accuracy', null)
            ->assertJsonPath('data.overall.tracked', 0)
            ->assertJsonPath('data.byTag', [])
            ->assertJsonPath('data.byTrack', [])
            ->assertJsonPath('data.hardestQuestions', []);
    }

    public function test_it_reports_accuracy_per_tag_and_per_track_worst_first(): void
    {
        $this->play($this->indexQuiz, true, true);
        $this->play($this->cacheQuiz, true, false);

        $response = $this->actingAs($this->user)->getJson('/api/v1/insights')->assertOk();

        $response
            ->assertJsonPath('data.overall.answered', 4)
            ->assertJsonPath('data.overall.correct', 3)
            ->assertJsonPath('data.overall.accuracy', 75)
            ->assertJsonPath('data.overall.tracked', 1);

        $response
            ->assertJsonPath('data.byTag.0.slug', 'redis')
            ->assertJsonPath('data.byTag.0.name', 'Redis')
            ->assertJsonPath('data.byTag.0.answered', 2)
            ->assertJsonPath('data.byTag.0.correct', 1)
            ->assertJsonPath('data.byTag.0.accuracy', 50)
            ->assertJsonPath('data.byTag.1.slug', 'postgres')
            ->assertJsonPath('data.byTag.1.accuracy', 100);

        $response
            ->assertJsonPath('data.byTrack.0.slug', 'caching')
            ->assertJsonPath('data.byTrack.0.accuracy', 50)
            ->assertJsonPath('data.byTrack.1.slug', 'indexing')
            ->assertJsonPath('data.byTrack.1.accuracy', 100);
    }

    public function test_an_untouched_tag_is_absent_rather_than_scored_zero(): void
    {
        Tag::create(['slug' => 'never-answered', 'name' => 'Never answered']);

        $this->play($this->indexQuiz, true, true);

        $slugs = $this->actingAs($this->user)->getJson('/api/v1/insights')->json('data.byTag.*.slug');

        $this->assertSame(['postgres'], $slugs);
    }

    public function test_the_hardest_questions_are_the_ones_missed_most_often(): void
    {
        $this->play($this->cacheQuiz, false, false);
        $this->play($this->indexQuiz, false, true);

        $missed = Question::where('track_id', $this->caching->id)->orderBy('key')->first();

        ReviewItem::where('user_id', $this->user->id)
            ->where('question_id', $missed->id)
            ->update(['lapses' => 4]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/insights')->assertOk();

        $response
            ->assertJsonPath('data.hardestQuestions.0.key', $missed->key)
            ->assertJsonPath('data.hardestQuestions.0.lapses', 4)
            ->assertJsonPath('data.hardestQuestions.0.trackSlug', 'caching')
            ->assertJsonPath('data.hardestQuestions.0.stem', "Stem for {$missed->key}");

        $this->assertLessThanOrEqual(5, count($response->json('data.hardestQuestions')));
    }

    public function test_a_question_answered_right_is_not_listed_as_hardest(): void
    {
        $this->play($this->indexQuiz, true, true);

        $this->actingAs($this->user)->getJson('/api/v1/insights')
            ->assertOk()
            ->assertJsonPath('data.hardestQuestions', []);
    }

    public function test_one_users_answers_never_leak_into_anothers_insights(): void
    {
        $this->play($this->cacheQuiz, false, false);

        $other = User::factory()->create(['locale' => 'en']);

        $this->actingAs($other)->getJson('/api/v1/insights')
            ->assertOk()
            ->assertJsonPath('data.overall.answered', 0)
            ->assertJsonPath('data.byTag', []);
    }

    public function test_it_serves_the_requested_locale_for_track_names(): void
    {
        $this->play($this->indexQuiz, true, false);

        $this->actingAs($this->user)->getJson('/api/v1/insights?locale=id')
            ->assertOk()
            ->assertJsonPath('data.byTrack.0.name', 'Jalur indexing');
    }

    public function test_the_report_does_not_grow_its_query_count_with_more_history(): void
    {
        $this->play($this->indexQuiz, true, false);

        $this->actingAs($this->user)->getJson('/api/v1/insights')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->user)->getJson('/api/v1/insights')->assertOk();
        $withOne = count(DB::getQueryLog());

        foreach (range(1, 6) as $ignored) {
            $this->play($this->cacheQuiz, false, true);
        }

        DB::flushQueryLog();
        $this->actingAs($this->user)->getJson('/api/v1/insights')->assertOk();
        $withSeven = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withOne, $withSeven, "query count grew from {$withOne} to {$withSeven}");
    }
}
