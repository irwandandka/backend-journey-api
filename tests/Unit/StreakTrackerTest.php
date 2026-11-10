<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StreakTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StreakTrackerTest extends TestCase
{
    use RefreshDatabase;

    private StreakTracker $streaks;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streaks = new StreakTracker;
        $this->user = User::factory()->create();
    }

    public function test_the_first_day_starts_the_streak_at_one(): void
    {
        $this->streaks->record($this->user, 2, 2, 50, Carbon::parse('2026-11-10'));

        $user = $this->user->fresh();
        $this->assertSame(1, $user->current_streak);
        $this->assertSame(1, $user->longest_streak);
        $this->assertSame('2026-11-10', $user->last_active_on->toDateString());
    }

    public function test_a_consecutive_day_increments_the_streak(): void
    {
        $this->streaks->record($this->user, 1, 1, 25, Carbon::parse('2026-11-10'));
        $this->streaks->record($this->user->fresh(), 1, 1, 25, Carbon::parse('2026-11-11'));

        $this->assertSame(2, $this->user->fresh()->current_streak);
    }

    public function test_a_second_session_on_the_same_day_does_not_increment(): void
    {
        $today = Carbon::parse('2026-11-10');

        $this->streaks->record($this->user, 1, 1, 25, $today);
        $this->streaks->record($this->user->fresh(), 3, 2, 40, $today);

        $user = $this->user->fresh();
        $this->assertSame(1, $user->current_streak);

        $row = DB::table('user_daily_activity')->where('user_id', $user->id)->sole();
        $this->assertSame(4, (int) $row->questions_answered);
        $this->assertSame(3, (int) $row->correct_answers);
        $this->assertSame(65, (int) $row->xp_earned);
        $this->assertSame(1, DB::table('user_daily_activity')->count());
    }

    public function test_a_gap_resets_the_streak_but_keeps_the_record(): void
    {
        $this->streaks->record($this->user, 1, 1, 25, Carbon::parse('2026-11-01'));
        $this->streaks->record($this->user->fresh(), 1, 1, 25, Carbon::parse('2026-11-02'));
        $this->streaks->record($this->user->fresh(), 1, 1, 25, Carbon::parse('2026-11-03'));
        $this->streaks->record($this->user->fresh(), 1, 1, 25, Carbon::parse('2026-11-09'));

        $user = $this->user->fresh();
        $this->assertSame(1, $user->current_streak);
        $this->assertSame(3, $user->longest_streak);
    }

    public function test_each_day_gets_its_own_activity_row(): void
    {
        $this->streaks->record($this->user, 1, 1, 25, Carbon::parse('2026-11-10'));
        $this->streaks->record($this->user->fresh(), 1, 0, 0, Carbon::parse('2026-11-11'));

        $this->assertSame(2, DB::table('user_daily_activity')->count());
    }
}
