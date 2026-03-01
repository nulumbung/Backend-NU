<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Google\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YouTubeOAuthController extends Controller
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(env('YOUTUBE_CLIENT_ID'));
        $this->client->setClientSecret(env('YOUTUBE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('YOUTUBE_REDIRECT_URI'));
        // We need full YouTube scope to create/edit broadcasts
        $this->client->setScopes([
            \Google\Service\YouTube::YOUTUBE,
            \Google\Service\YouTube::YOUTUBE_FORCE_SSL
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function redirect(Request $request)
    {
        $authUrl = $this->client->createAuthUrl();
        return response()->json(['url' => $authUrl]);
    }

    public function callback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            Log::error('YouTube OAuth Callback: No code provided');
            return redirect()->to(env('FRONTEND_URL', 'http://localhost:3000') . '/admin/live-streams?youtube_auth=error');
        }

        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                Log::error('YouTube OAuth Fetch Token Error: ' . json_encode($token));
                return redirect()->to(env('FRONTEND_URL', 'http://localhost:3000') . '/admin/live-streams?youtube_auth=error');
            }

            // For this CMS, we usually just link one global YouTube account,
            // so we can just store one record or update the first one.
            $existing = DB::table('youtube_oauth_tokens')->first();
            
            $data = [
                'access_token' => $token['access_token'],
                'expires_in' => $token['expires_in'],
                'created' => $token['created'],
                'updated_at' => now(),
            ];

            // Only update refresh token if we got a new one (Google only sends it on first consent)
            if (isset($token['refresh_token'])) {
                $data['refresh_token'] = $token['refresh_token'];
            }

            if ($existing) {
                DB::table('youtube_oauth_tokens')->where('id', $existing->id)->update($data);
            } else {
                $data['created_at'] = now();
                DB::table('youtube_oauth_tokens')->insert($data);
            }

            return redirect()->to(env('FRONTEND_URL', 'http://localhost:3000') . '/admin/live-streams?youtube_auth=success');

        } catch (\Exception $e) {
            Log::error('YouTube OAuth Exception: ' . $e->getMessage());
            return redirect()->to(env('FRONTEND_URL', 'http://localhost:3000') . '/admin/live-streams?youtube_auth=error');
        }
    }
    
    public function status()
    {
        $token = DB::table('youtube_oauth_tokens')->first();
        if ($token) {
            return response()->json(['connected' => true]);
        }
        return response()->json(['connected' => false]);
    }
    
    public function disconnect()
    {
        DB::table('youtube_oauth_tokens')->truncate();
        return response()->json(['message' => 'Disconnected']);
    }
}
