<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QuizAttemptAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = ['quiz_attempt_question_id', 'is_correct', 'time_spent_ms', 'answered_at'];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'time_spent_ms' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizAttemptQuestion::class, 'quiz_attempt_question_id');
    }

    public function selectedOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            QuestionOption::class,
            'attempt_answer_options',
            'quiz_attempt_answer_id',
            'question_option_id',
        );
    }
}
