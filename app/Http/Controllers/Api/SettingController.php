<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $settings = Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get();
        return response()->json($settings);
    }

    /**
     * Update or create settings in batch.
     */
    public function updateBatch(Request $request)
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable', // Value can be anything or null
        ]);

        $updatedSettings = [];

        DB::transaction(function () use (&$updatedSettings, $data) {
            foreach ($data['settings'] as $item) {
                $setting = Setting::firstOrNew(['key' => $item['key']]);
                $incomingValue = $item['value'];

                if (!$setting->exists) {
                    $setting->group = 'general';
                    $setting->type = 'text';
                }

                if (is_array($incomingValue) || is_object($incomingValue)) {
                    $incomingValue = json_encode($incomingValue);
                }

                $setting->value = $incomingValue;
                $setting->save();
                $updatedSettings[] = $setting;
            }
        });

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => $updatedSettings
        ]);
    }

    /**
     * Public endpoint to get settings (filtered for public consumption)
     */
    public function publicSettings()
    {
        // Only return settings that are safe for public
        $publicKeys = [
            'site_title', 
            'site_description', 
            'site_logo', 
            'site_favicon',
            'contact_email',
            'contact_phone',
            'contact_address',
            'social_facebook',
            'social_twitter',
            'social_instagram',
            'social_youtube',
            'seo_meta_keywords',
            'seo_meta_description',
            'legal_privacy_policy',
            'legal_terms_conditions',
            'history_hero_background_image',
            'history_hero_tagline',
        ];

        $settings = Setting::whereIn('key', $publicKeys)->get();
        
        // Transform to key-value pair for easier frontend usage
        $formatted = $settings->mapWithKeys(function ($item) {
            return [$item->key => $item->value];
        });

        $settingsHash = md5(
            $settings
                ->mapWithKeys(fn ($item) => [$item->key => $item->value])
                ->sortKeys()
                ->toJson()
        );
        $formatted['settings_version'] = $settingsHash;

        return response()->json($formatted)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
