<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General Settings
            ['key' => 'site_title', 'value' => 'NU Lumbung', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_description', 'value' => 'Portal Berita dan Informasi Nahdlatul Ulama', 'group' => 'general', 'type' => 'textarea'],
            ['key' => 'site_logo', 'value' => '', 'group' => 'general', 'type' => 'image'],
            ['key' => 'site_favicon', 'value' => '', 'group' => 'general', 'type' => 'image'],
            
            // Contact Information
            ['key' => 'contact_email', 'value' => 'info@nulumbung.or.id', 'group' => 'contact', 'type' => 'text'],
            ['key' => 'contact_phone', 'value' => '+62 123 4567 890', 'group' => 'contact', 'type' => 'text'],
            ['key' => 'contact_address', 'value' => 'Jl. Kramat Raya No.164, Jakarta Pusat', 'group' => 'contact', 'type' => 'textarea'],
            
            // Social Media
            ['key' => 'social_facebook', 'value' => 'https://facebook.com/nahdlatululama', 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_twitter', 'value' => 'https://twitter.com/nahdlatululama', 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_instagram', 'value' => 'https://instagram.com/nahdlatululama', 'group' => 'social', 'type' => 'text'],
            ['key' => 'social_youtube', 'value' => 'https://youtube.com/nahdlatululama', 'group' => 'social', 'type' => 'text'],
            
            // SEO
            ['key' => 'seo_meta_keywords', 'value' => 'nu, nahdlatul ulama, islam nusantara, aswaja', 'group' => 'seo', 'type' => 'textarea'],
            ['key' => 'seo_meta_description', 'value' => 'Situs resmi Nahdlatul Ulama Lumbung informasi.', 'group' => 'seo', 'type' => 'textarea'],

            // Legal & Pages
            ['key' => 'legal_privacy_policy', 'value' => '# Kebijakan Privasi...', 'group' => 'legal', 'type' => 'richtext'],
            ['key' => 'legal_terms_conditions', 'value' => '# Syarat & Ketentuan...', 'group' => 'legal', 'type' => 'richtext'],

            // History Hero Visual
            ['key' => 'history_hero_background_image', 'value' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e8/Lawang_sewu_semarang.jpg/1600px-Lawang_sewu_semarang.jpg', 'group' => 'history', 'type' => 'image'],
            ['key' => 'history_hero_tagline', 'value' => 'Perjalanan Panjang Nahdlatul Ulama Dari Masa ke Masa', 'group' => 'history', 'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
