<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debate extends Model
{
    protected $fillable = ['user_id', 'title', 'slug', 'category', 'description', 'starts_at', 'ends_at', 'published_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
