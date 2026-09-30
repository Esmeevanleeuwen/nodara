<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = ['article_id', 'user_id', 'body'];

    protected function casts(): array
    {
        return [];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
