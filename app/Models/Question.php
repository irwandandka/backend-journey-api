<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    public const TYPES = ['single_choice', 'multi_choice', 'true_false'];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    protected $fillable = ['key', 'track_id', 'type', 'difficulty', 'current_version_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class)->orderBy('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'current_version_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_tag')->orderBy('slug');
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_questions')->withPivot('position');
    }

    public function reviewItems(): HasMany
    {
        return $this->hasMany(ReviewItem::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->whereNotNull('current_version_id');
    }

    public function xpValue(): int
    {
        return match ($this->difficulty) {
            'easy' => 15,
            'hard' => 40,
            default => 25,
        };
    }
}
