<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id', 'quiz_id', 'kind', 'status', 'locale',
        'question_count', 'correct_count', 'score', 'started_at', 'submitted_at',
    ];

    protected $attributes = [
        'kind' => 'quiz',
        'status' => 'in_progress',
        'correct_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'question_count' => 'integer',
            'correct_count' => 'integer',
            'score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(QuizAttemptQuestion::class)->orderBy('position');
    }

    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', 'in_progress');
    }

    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    public function isReview(): bool
    {
        return $this->kind === 'review';
    }
}
