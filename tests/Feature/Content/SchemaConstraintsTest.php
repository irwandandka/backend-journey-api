<?php

namespace Tests\Feature\Content;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptQuestion;
use App\Models\ReviewItem;
use App\Models\User;
use App\Models\UserJourneyProgress;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        app(ContentSeeder::class)->sync(ContentFixture::minimal());

        $this->user = User::factory()->create();
        $this->quiz = Quiz::sole();
    }

    private function attempt(string $status): QuizAttempt
    {
        return QuizAttempt::create([
            'user_id' => $this->user->id,
            'quiz_id' => $this->quiz->id,
            'status' => $status,
            'locale' => 'en',
            'question_count' => 1,
            'started_at' => now(),
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);
    }

    public function test_a_user_can_only_have_one_in_progress_attempt_per_quiz(): void
    {
        $this->attempt('in_progress');

        $this->expectException(QueryException::class);
        $this->attempt('in_progress');
    }

    public function test_a_user_can_have_many_submitted_attempts_on_the_same_quiz(): void
    {
        $this->attempt('submitted');
        $this->attempt('submitted');
        $this->attempt('in_progress');

        $this->assertSame(3, QuizAttempt::count());
    }

    public function test_journey_progress_is_unique_per_user_and_journey(): void
    {
        $journey = $this->quiz->journey;

        UserJourneyProgress::create([
            'user_id' => $this->user->id,
            'journey_id' => $journey->id,
            'status' => 'in_progress',
        ]);

        $this->expectException(QueryException::class);
        UserJourneyProgress::create([
            'user_id' => $this->user->id,
            'journey_id' => $journey->id,
            'status' => 'completed',
        ]);
    }

    public function test_a_review_item_is_unique_per_user_and_question(): void
    {
        $question = Question::sole();

        ReviewItem::create([
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'due_at' => now()->addDay(),
        ]);

        $this->expectException(QueryException::class);
        ReviewItem::create([
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'due_at' => now()->addDays(3),
        ]);
    }

    public function test_deleting_a_version_removes_its_options_and_translations(): void
    {
        $question = Question::sole();
        $version = $question->currentVersion;

        $this->assertSame(2, $version->options()->count());

        $question->update(['current_version_id' => null]);
        $version->delete();

        $this->assertSame(0, QuestionOption::count());
        $this->assertSame(0, DB::table('question_option_translations')->count());
        $this->assertSame(0, DB::table('question_version_translations')->count());
    }

    public function test_a_version_served_in_an_attempt_cannot_be_deleted(): void
    {
        $attempt = $this->attempt('in_progress');
        $version = QuestionVersion::sole();

        QuizAttemptQuestion::create([
            'quiz_attempt_id' => $attempt->id,
            'question_version_id' => $version->id,
            'position' => 0,
            'snapshot' => ['stem_md' => 'frozen copy'],
        ]);

        $this->expectException(QueryException::class);
        $version->delete();
    }

    public function test_the_attempt_snapshot_round_trips_as_an_array(): void
    {
        $attempt = $this->attempt('in_progress');

        QuizAttemptQuestion::create([
            'quiz_attempt_id' => $attempt->id,
            'question_version_id' => QuestionVersion::sole()->id,
            'position' => 0,
            'snapshot' => ['stem_md' => 'frozen', 'options' => [['label_md' => 'a'], ['label_md' => 'b']]],
        ]);

        $stored = QuizAttemptQuestion::sole();

        $this->assertSame('frozen', $stored->snapshot['stem_md']);
        $this->assertCount(2, $stored->snapshot['options']);
    }

    public function test_due_scope_only_returns_items_at_or_past_their_due_time(): void
    {
        $question = Question::sole();

        ReviewItem::create([
            'user_id' => $this->user->id,
            'question_id' => $question->id,
            'due_at' => now()->subMinute(),
        ]);

        ReviewItem::create([
            'user_id' => User::factory()->create()->id,
            'question_id' => $question->id,
            'due_at' => now()->addDay(),
        ]);

        $this->assertSame(1, ReviewItem::query()->due()->count());
    }
}
