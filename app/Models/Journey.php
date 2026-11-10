<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journey extends Model
{
    use HasTranslations;

    public const TIERS = ['foundation', 'intermediate', 'advanced', 'deep'];

    protected $fillable = [
        'track_id', 'slug', 'tier', 'unlock_rule', 'position', 'pass_threshold', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'pass_threshold' => 'integer',
        ];
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class)->orderBy('position');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserJourneyProgress::class);
    }
}
