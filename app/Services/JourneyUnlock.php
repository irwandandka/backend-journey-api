<?php

namespace App\Services;

use App\Models\Journey;
use Illuminate\Support\Collection;

class JourneyUnlock
{
    public const TIER_ORDER = ['foundation', 'intermediate', 'advanced', 'deep'];

    /**
     * @param  Collection<int, Journey>  $siblings  every published journey of the track, ordered by position
     * @param  Collection<int, \App\Models\UserJourneyProgress>  $progress  keyed by journey id
     */
    public function statusFor(Journey $journey, Collection $siblings, Collection $progress): string
    {
        $own = $progress->get($journey->id);

        if ($own !== null && $own->status !== 'available') {
            return $own->status;
        }

        return $this->isUnlocked($journey, $siblings, $progress) ? 'available' : 'locked';
    }

    /**
     * @param  Collection<int, Journey>  $siblings
     * @param  Collection<int, \App\Models\UserJourneyProgress>  $progress
     */
    public function isUnlocked(Journey $journey, Collection $siblings, Collection $progress): bool
    {
        if ($journey->unlock_rule === 'open') {
            return true;
        }

        $completed = fn (Journey $candidate): bool => $progress->get($candidate->id)?->status === 'completed';

        if ($journey->unlock_rule === 'tier') {
            $ownTier = array_search($journey->tier, self::TIER_ORDER, true);

            return $siblings
                ->filter(fn (Journey $s) => array_search($s->tier, self::TIER_ORDER, true) < $ownTier)
                ->every($completed);
        }

        $ordered = $siblings->values();
        $index = $ordered->search(fn (Journey $s) => $s->id === $journey->id);

        if ($index === false || $index === 0) {
            return true;
        }

        return $completed($ordered[$index - 1]);
    }
}
