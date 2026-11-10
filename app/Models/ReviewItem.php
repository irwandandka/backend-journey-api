<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewItem extends Model
{
    protected $fillable = [
        'user_id', 'question_id', 'due_at', 'interval_days',
        'ease_factor', 'repetitions', 'lapses', 'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
            'interval_days' => 'integer',
            'ease_factor' => 'float',
            'repetitions' => 'integer',
            'lapses' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function scopeDue(Builder $query, ?\DateTimeInterface $at = null): void
    {
        $query->where('due_at', '<=', $at ?? now());
    }
}
