<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyActivity extends Model
{
    public $timestamps = false;

    protected $table = 'user_daily_activity';

    protected $fillable = [
        'user_id', 'activity_date', 'questions_answered', 'correct_answers', 'xp_earned',
    ];

    protected function casts(): array
    {
        return ['activity_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
