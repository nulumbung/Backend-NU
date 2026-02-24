<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            [
                'key' => 'history_hero_background_image',
                'value' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e8/Lawang_sewu_semarang.jpg/1600px-Lawang_sewu_semarang.jpg',
                'group' => 'history',
                'type' => 'image',
            ],
            [
                'key' => 'history_hero_background_overlay_image',
                'value' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f8/Aerial_view_of_Bajra_Sandhi_Monument_Denpasar_Bali_Indonesia.jpg/1600px-Aerial_view_of_Bajra_Sandhi_Monument_Denpasar_Bali_Indonesia.jpg',
                'group' => 'history',
                'type' => 'image',
            ],
            [
                'key' => 'history_hero_tagline',
                'value' => 'Perjalanan Panjang Nahdlatul Ulama Dari Masa ke Masa',
                'group' => 'history',
                'type' => 'text',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                    'type' => $setting['type'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'history_hero_background_image',
                'history_hero_background_overlay_image',
                'history_hero_tagline',
            ])
            ->delete();
    }
};
