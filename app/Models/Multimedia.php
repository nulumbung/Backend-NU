<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Multimedia extends Model
{
    protected $fillable = [
        'title', 'slug', 'type', 'thumbnail', 'url', 'gallery', 'description', 'date', 'author', 'tags', 'views', 'likes'
    ];

    protected $casts = [
        'gallery' => 'array',
        'tags' => 'array',
        'date' => 'datetime',
    ];

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
