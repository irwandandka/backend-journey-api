<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuizAttemptQuestion extends Model
{
    public $timestamps = false;

    protected $fillable = ['quiz_attempt_id', 'question_version_id', 'position', 'snapshot'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'position' => 'integer',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class);
    }

    public function answer(): HasOne
    {
        return $this->hasOne(QuizAttemptAnswer::class, 'quiz_attempt_question_id');
    }
}
