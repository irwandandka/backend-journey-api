<?php

namespace App\Services;

use App\Models\ReviewItem;
use Illuminate\Support\Carbon;

class ReviewScheduler
{
    public const MIN_EASE = 1.3;

    public const MAX_EASE = 2.8;

    public const MAX_INTERVAL_DAYS = 365;

    public function nextInterval(int $repetitions, int $previousInterval, float $ease): int
    {
        $interval = match (true) {
            $repetitions <= 0 => 1,
            $repetitions === 1 => 3,
            default => (int) round(max(1, $previousInterval) * $ease),
        };

        return max(1, min(self::MAX_INTERVAL_DAYS, $interval));
    }

    public function recordLapse(int $userId, int $questionId, ?Carbon $now = null): ReviewItem
    {
        $now ??= Carbon::now();

        $item = ReviewItem::firstOrNew([
            'user_id' => $userId,
            'question_id' => $questionId,
        ]);

        $item->fill([
            'repetitions' => 0,
            'interval_days' => 1,
            'lapses' => $item->lapses + 1,
            'ease_factor' => max(self::MIN_EASE, round(($item->ease_factor ?? 2.5) - 0.2, 2)),
            'due_at' => $now->copy()->addDay(),
            'last_reviewed_at' => $now,
        ]);

        $item->save();

        return $item;
    }

    public function recordSuccess(int $userId, int $questionId, ?Carbon $now = null): ?ReviewItem
    {
        $now ??= Carbon::now();

        $item = ReviewItem::where('user_id', $userId)->where('question_id', $questionId)->first();

        if ($item === null) {
            return null;
        }

        $repetitions = $item->repetitions + 1;
        $ease = min(self::MAX_EASE, round($item->ease_factor + 0.05, 2));
        $interval = $this->nextInterval($repetitions, $item->interval_days, $ease);

        $item->fill([
            'repetitions' => $repetitions,
            'ease_factor' => $ease,
            'interval_days' => $interval,
            'due_at' => $now->copy()->addDays($interval),
            'last_reviewed_at' => $now,
        ])->save();

        return $item;
    }
}
