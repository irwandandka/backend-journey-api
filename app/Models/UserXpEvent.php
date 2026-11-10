<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserXpEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'source_type', 'source_id', 'amount', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
