<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LiveStream extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'youtube_id',
        'channel_name',
        'thumbnail_url',
        'view_count',
        'is_active',
        'status',
        'scheduled_start_time',
        'actual_start_time',
        'actual_end_time',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'scheduled_start_time' => 'datetime',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'view_count' => 'integer',
    ];

    /**
     * Scope a query to only include active streams.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include upcoming streams.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')
                     ->where('scheduled_start_time', '>', now())
                     ->orderBy('scheduled_start_time', 'asc');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
