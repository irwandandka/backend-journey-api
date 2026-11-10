<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserJourneyProgress extends Model
{
    protected $table = 'user_journey_progress';

    protected $fillable = [
        'user_id', 'journey_id', 'status', 'best_score', 'attempts_count', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'best_score' => 'integer',
            'attempts_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }
}
