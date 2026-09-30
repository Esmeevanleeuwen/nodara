<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    protected $fillable = ['debate_id', 'participant_id', 'user_id'];

    protected function casts(): array
    {
        return [];
    }
}
