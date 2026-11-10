<?php

namespace Tests\Unit;

use App\Models\Journey;
use App\Models\UserJourneyProgress;
use App\Services\JourneyUnlock;
use Illuminate\Support\Collection;
use Tests\TestCase;

class JourneyUnlockTest extends TestCase
{
    /** @param list<array{int, string, string}> $specs id, tier, rule */
    private function siblings(array $specs): Collection
    {
        return collect($specs)->map(function (array $spec) {
            $journey = new Journey(['tier' => $spec[1], 'unlock_rule' => $spec[2], 'position' => $spec[0]]);
            $journey->id = $spec[0];

            return $journey;
        });
    }

    /** @param array<int, string> $statuses */
    private function progress(array $statuses): Collection
    {
        return collect($statuses)->map(function (string $status, int $journeyId) {
            $row = new UserJourneyProgress(['status' => $status]);
            $row->journey_id = $journeyId;

            return $row;
        })->keyBy('journey_id');
    }

    private JourneyUnlock $unlock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unlock = new JourneyUnlock;
    }

    public function test_an_open_journey_never_locks(): void
    {
        $siblings = $this->siblings([[1, 'foundation', 'sequential'], [2, 'deep', 'open']]);

        $this->assertTrue($this->unlock->isUnlocked($siblings[1], $siblings, collect()));
    }

    public function test_sequential_needs_the_previous_journey_completed(): void
    {
        $siblings = $this->siblings([
            [1, 'foundation', 'sequential'],
            [2, 'foundation', 'sequential'],
            [3, 'foundation', 'sequential'],
        ]);

        $this->assertTrue($this->unlock->isUnlocked($siblings[0], $siblings, collect()));
        $this->assertFalse($this->unlock->isUnlocked($siblings[1], $siblings, collect()));

        $progress = $this->progress([1 => 'completed']);

        $this->assertTrue($this->unlock->isUnlocked($siblings[1], $siblings, $progress));
        $this->assertFalse($this->unlock->isUnlocked($siblings[2], $siblings, $progress));
    }

    public function test_an_in_progress_previous_journey_does_not_unlock_the_next(): void
    {
        $siblings = $this->siblings([[1, 'foundation', 'sequential'], [2, 'foundation', 'sequential']]);

        $this->assertFalse(
            $this->unlock->isUnlocked($siblings[1], $siblings, $this->progress([1 => 'in_progress'])),
        );
    }

    public function test_tier_needs_every_earlier_tier_completed_and_ignores_its_own_tier(): void
    {
        $siblings = $this->siblings([
            [1, 'foundation', 'tier'],
            [2, 'foundation', 'tier'],
            [3, 'intermediate', 'tier'],
            [4, 'intermediate', 'tier'],
        ]);

        $this->assertFalse($this->unlock->isUnlocked($siblings[2], $siblings, $this->progress([1 => 'completed'])));

        $bothFoundation = $this->progress([1 => 'completed', 2 => 'completed']);

        $this->assertTrue($this->unlock->isUnlocked($siblings[2], $siblings, $bothFoundation));
        $this->assertTrue(
            $this->unlock->isUnlocked($siblings[3], $siblings, $bothFoundation),
            'a sibling in the same tier must not block',
        );
    }

    public function test_the_first_tier_is_unlocked_with_no_progress_at_all(): void
    {
        $siblings = $this->siblings([[1, 'foundation', 'tier'], [2, 'advanced', 'tier']]);

        $this->assertTrue($this->unlock->isUnlocked($siblings[0], $siblings, collect()));
    }

    public function test_status_prefers_a_recorded_status_over_the_unlock_rule(): void
    {
        $siblings = $this->siblings([[1, 'foundation', 'sequential'], [2, 'foundation', 'sequential']]);

        $this->assertSame(
            'in_progress',
            $this->unlock->statusFor($siblings[1], $siblings, $this->progress([2 => 'in_progress'])),
        );
    }
}
