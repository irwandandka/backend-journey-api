<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Models\ReviewItem;
use App\Models\Roadmap;
use App\Models\Track;
use App\Models\User;
use App\Services\ReviewScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private ReviewScheduler $scheduler;

    private User $user;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduler = new ReviewScheduler;
        $this->user = User::factory()->create();

        $roadmap = Roadmap::create(['slug' => 'r', 'is_published' => true]);
        $track = Track::create([
            'roadmap_id' => $roadmap->id, 'slug' => 't', 'abbreviation' => 'TT',
            'color_dark' => '#000000', 'color_light' => '#ffffff', 'is_published' => true,
        ]);
        $this->question = Question::create(['key' => 'q', 'track_id' => $track->id]);
    }

    /** @return array<string, array{int, int, float, int}> */
    public static function intervals(): array
    {
        return [
            'first repetition is one day' => [0, 0, 2.5, 1],
            'second repetition is three days' => [1, 1, 2.5, 3],
            'third multiplies by ease' => [2, 3, 2.5, 8],
            'ease floor still advances' => [2, 3, 1.3, 4],
            'never returns zero' => [5, 0, 1.3, 1],
            'negative repetitions behave like the first' => [-1, 9, 2.5, 1],
            'the interval is capped at a year' => [9, 20000, 2.5, 365],
        ];
    }

    #[DataProvider('intervals')]
    public function test_it_computes_the_next_interval(int $reps, int $previous, float $ease, int $expected): void
    {
        $this->assertSame($expected, $this->scheduler->nextInterval($reps, $previous, $ease));
    }

    public function test_a_first_lapse_creates_an_item_due_tomorrow(): void
    {
        $now = Carbon::parse('2026-11-10 09:00:00');

        $item = $this->scheduler->recordLapse($this->user->id, $this->question->id, $now);

        $this->assertSame(1, $item->lapses);
        $this->assertSame(0, $item->repetitions);
        $this->assertSame(1, $item->interval_days);
        $this->assertSame(2.3, $item->ease_factor);
        $this->assertSame('2026-11-11 09:00:00', $item->due_at->toDateTimeString());
    }

    public function test_repeated_lapses_lower_the_ease_down_to_a_floor(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $item = $this->scheduler->recordLapse($this->user->id, $this->question->id);
        }

        $this->assertSame(10, $item->lapses);
        $this->assertSame(ReviewScheduler::MIN_EASE, $item->ease_factor);
        $this->assertSame(1, ReviewItem::count());
    }

    public function test_success_on_a_question_that_was_never_missed_creates_nothing(): void
    {
        $this->assertNull($this->scheduler->recordSuccess($this->user->id, $this->question->id));
        $this->assertSame(0, ReviewItem::count());
    }

    public function test_success_pushes_the_due_date_out_and_raises_the_ease(): void
    {
        $now = Carbon::parse('2026-11-10 09:00:00');
        $this->scheduler->recordLapse($this->user->id, $this->question->id, $now);

        $first = $this->scheduler->recordSuccess($this->user->id, $this->question->id, $now);
        $this->assertSame(1, $first->repetitions);
        $this->assertSame(3, $first->interval_days);
        $this->assertSame(2.35, $first->ease_factor);
        $this->assertSame('2026-11-13 09:00:00', $first->due_at->toDateTimeString());

        $second = $this->scheduler->recordSuccess($this->user->id, $this->question->id, $now);
        $this->assertSame(2, $second->repetitions);
        $this->assertSame(7, $second->interval_days);
        $this->assertSame('2026-11-17 09:00:00', $second->due_at->toDateTimeString());
    }

    public function test_the_ease_and_interval_never_rise_above_their_caps(): void
    {
        $this->scheduler->recordLapse($this->user->id, $this->question->id);

        for ($i = 0; $i < 30; $i++) {
            $item = $this->scheduler->recordSuccess($this->user->id, $this->question->id);
        }

        $this->assertSame(ReviewScheduler::MAX_EASE, $item->ease_factor);
        $this->assertSame(ReviewScheduler::MAX_INTERVAL_DAYS, $item->interval_days);
        $this->assertLessThan(32767, $item->interval_days, 'must stay inside a Postgres smallint');
    }
}
