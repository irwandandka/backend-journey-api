<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasTranslations;

    public $timestamps = false;

    protected $fillable = ['slug', 'icon', 'rule', 'position'];

    protected function casts(): array
    {
        return ['rule' => 'array'];
    }
}
