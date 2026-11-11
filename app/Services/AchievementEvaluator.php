<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\ReviewItem;
use App\Models\User;
use App\Models\UserJourneyProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AchievementEvaluator
{
    public const RULE_TYPES = ['journeys_completed', 'streak_days', 'review_queue_cleared'];

    /**
     * Unlock whatever the user has now earned and return only what was newly unlocked.
     *
     * @return Collection<int, Achievement>
     */
    public function evaluate(User $user, ?string $locale = null): Collection
    {
        $candidates = Achievement::query()
            ->withTranslations($locale)
            ->whereNotExists(function ($query) use ($user) {
                $query->selectRaw('1')
                    ->from('user_achievements')
                    ->whereColumn('user_achievements.achievement_id', 'achievements.id')
                    ->where('user_achievements.user_id', $user->id);
            })
            ->orderBy('position')
            ->get();

        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $metrics = $this->metricsFor($user, $candidates);

        $earned = $candidates->filter(fn (Achievement $achievement) => $this->isEarned($achievement, $metrics));

        if ($earned->isEmpty()) {
            return $earned->values();
        }

        DB::table('user_achievements')->insertOrIgnore(
            $earned->map(fn (Achievement $achievement) => [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at' => now(),
            ])->all(),
        );

        return $earned->values();
    }

    /**
     * Only the metrics the pending rules actually ask for, so an unlocked
     * account stops querying for them entirely.
     *
     * @param  Collection<int, Achievement>  $candidates
     * @return array<string, int>
     */
    private function metricsFor(User $user, Collection $candidates): array
    {
        $needed = $candidates
            ->map(fn (Achievement $achievement) => (string) ($achievement->rule['type'] ?? ''))
            ->unique();

        $metrics = [];

        if ($needed->contains('journeys_completed')) {
            $metrics['journeys_completed'] = UserJourneyProgress::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();
        }

        if ($needed->contains('streak_days')) {
            $metrics['streak_days'] = max($user->current_streak, $user->longest_streak);
        }

        if ($needed->contains('review_queue_cleared')) {
            $counts = ReviewItem::query()
                ->where('user_id', $user->id)
                ->selectRaw('count(*) as tracked_count')
                ->selectRaw('count(*) filter (where due_at <= ?) as due_count', [now()])
                ->first();

            $tracked = (int) $counts->tracked_count;
            $due = (int) $counts->due_count;

            $metrics['review_queue_cleared'] = $tracked > 0 && $due === 0 ? 1 : 0;
        }

        return $metrics;
    }

    /** @param array<string, int> $metrics */
    private function isEarned(Achievement $achievement, array $metrics): bool
    {
        $type = (string) ($achievement->rule['type'] ?? '');
        $target = (int) ($achievement->rule['count'] ?? 0);

        if (! array_key_exists($type, $metrics) || $target < 1) {
            return false;
        }

        return $metrics[$type] >= $target;
    }
}
