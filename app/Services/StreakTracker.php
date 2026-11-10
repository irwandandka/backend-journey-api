<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDailyActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StreakTracker
{
    public function record(User $user, int $answered, int $correct, int $xp, ?Carbon $today = null): void
    {
        $today ??= Carbon::today();
        $last = $user->last_active_on;

        $current = match (true) {
            $last === null => 1,
            $last->isSameDay($today) => max(1, $user->current_streak),
            $last->isSameDay($today->copy()->subDay()) => $user->current_streak + 1,
            default => 1,
        };

        $user->forceFill([
            'current_streak' => $current,
            'longest_streak' => max($user->longest_streak, $current),
            'last_active_on' => $today,
        ])->save();

        DB::table('user_daily_activity')->upsert(
            [[
                'user_id' => $user->id,
                'activity_date' => $today->toDateString(),
                'questions_answered' => $answered,
                'correct_answers' => $correct,
                'xp_earned' => $xp,
            ]],
            ['user_id', 'activity_date'],
            [
                'questions_answered' => DB::raw('user_daily_activity.questions_answered + excluded.questions_answered'),
                'correct_answers' => DB::raw('user_daily_activity.correct_answers + excluded.correct_answers'),
                'xp_earned' => DB::raw('user_daily_activity.xp_earned + excluded.xp_earned'),
            ],
        );
    }
}
