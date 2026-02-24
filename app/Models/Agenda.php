<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
{
    protected $fillable = [
        'title', 'slug', 'description', 'date_start', 'date_end', 'time_string', 
        'location', 'maps_url', 'image', 'status', 'rundown', 'gallery', 'ticket_price',
        'ticket_quota', 'ticket_quota_label', 'ticket_info_title', 'organizer', 'organizer_logo',
        'organizer_verified', 'registration_enabled', 'registration_url', 'registration_button_text',
        'registration_note', 'registration_closed_text', 'registration_open_until'
    ];

    protected $casts = [
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'rundown' => 'array',
        'gallery' => 'array',
        'organizer_verified' => 'boolean',
        'registration_enabled' => 'boolean',
        'registration_open_until' => 'datetime',
    ];
}
