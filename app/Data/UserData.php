<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $locale,
        public int $xpTotal,
        public int $currentStreak,
        public int $longestStreak,
        public ?string $lastActiveOn,
        /** @var list<string> */
        public array $roles,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            locale: $user->locale,
            xpTotal: $user->xp_total,
            currentStreak: $user->current_streak,
            longestStreak: $user->longest_streak,
            lastActiveOn: $user->last_active_on?->toDateString(),
            roles: $user->getRoleNames()->values()->all(),
        );
    }
}
