<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $fillable = ['debate_id', 'name', 'position', 'argument'];

    protected function casts(): array
    {
        return [];
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }
}
