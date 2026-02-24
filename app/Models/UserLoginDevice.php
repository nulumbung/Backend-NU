<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_fingerprint',
        'device_name',
        'ip_address',
        'user_agent',
        'login_provider',
        'login_count',
        'last_login_at',
        'last_seen_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
