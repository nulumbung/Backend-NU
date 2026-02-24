<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'history_hero_background_overlay_image')
            ->delete();
    }

    public function down(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'history_hero_background_overlay_image'],
            [
                'value' => '',
                'group' => 'history',
                'type' => 'image',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
