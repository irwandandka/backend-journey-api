<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
    use HasTranslations;

    protected $fillable = [
        'roadmap_id', 'slug', 'abbreviation', 'color_dark', 'color_light', 'position', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(Roadmap::class);
    }

    public function journeys(): HasMany
    {
        return $this->hasMany(Journey::class)->orderBy('position');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function color(?bool $light = null): string
    {
        return ($light ?? false) ? $this->color_light : $this->color_dark;
    }
}
