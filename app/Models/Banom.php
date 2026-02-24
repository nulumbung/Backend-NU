<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banom extends Model
{
    protected $fillable = [
        'name', 'slug', 'logo', 'short_desc', 'long_desc', 'history', 'vision', 'mission', 'management'
    ];

    protected $casts = [
        'mission' => 'array',
        'management' => 'array',
    ];
}
