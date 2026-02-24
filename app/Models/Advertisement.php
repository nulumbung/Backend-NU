<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Advertisement extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'placement',
        'content_type',
        'image_url',
        'html_content',
        'target_url',
        'alt_text',
        'is_active',
        'starts_at',
        'ends_at',
        'priority',
        'impressions',
        'clicks',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
}
