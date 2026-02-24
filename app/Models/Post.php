<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'image', 'author_id', 
        'category_id', 'status', 'published_at', 'views', 'read_time', 
        'is_featured', 'is_spotlight', 'is_breaking', 'is_headline', 'tags', 'image_caption', 'image_credit'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_spotlight' => 'boolean',
        'is_breaking' => 'boolean',
        'is_headline' => 'boolean',
        'tags' => 'array',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
