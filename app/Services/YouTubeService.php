<?php

namespace App\Services;

use Google\Client;
use Google\Service\YouTube;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    protected $client;
    protected $youtube;

    public function __construct()
    {
        $this->client = new Client();
        
        // Use config() instead of env() to support php artisan config:cache
        $apiKey = config('services.youtube.api_key', env('YOUTUBE_API_KEY'));
        if (!empty($apiKey)) {
            $this->client->setDeveloperKey($apiKey);
        }

        // Add Referer header to bypass Google API Key HTTP referrer restrictions
        $httpClient = new \GuzzleHttp\Client([
            'headers' => [
                'referer' => config('app.url', env('APP_URL', 'http://127.0.0.1:8000'))
            ]
        ]);
        $this->client->setHttpClient($httpClient);

        $this->youtube = new YouTube($this->client);
    }

    /**
     * Authenticate the client using token from database.
     */
    public function authenticateWithDbToken()
    {
        $tokenRow = \Illuminate\Support\Facades\DB::table('youtube_oauth_tokens')->first();
        if (!$tokenRow) {
            throw new \Exception('Not authenticated with YouTube.');
        }

        $tokenArray = [
            'access_token' => $tokenRow->access_token,
            'expires_in' => $tokenRow->expires_in,
            'created' => $tokenRow->created,
            'refresh_token' => $tokenRow->refresh_token,
        ];
        
        $this->client->setClientId(env('YOUTUBE_CLIENT_ID'));
        $this->client->setClientSecret(env('YOUTUBE_CLIENT_SECRET'));
        $this->client->setAccessToken($tokenArray);

        if ($this->client->isAccessTokenExpired()) {
            if ($tokenRow->refresh_token) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($tokenRow->refresh_token);
                if (!isset($newToken['error'])) {
                    \Illuminate\Support\Facades\DB::table('youtube_oauth_tokens')
                        ->where('id', $tokenRow->id)
                        ->update([
                            'access_token' => $newToken['access_token'],
                            'expires_in' => $newToken['expires_in'],
                            'created' => $newToken['created'],
                            'updated_at' => now(),
                        ]);
                } else {
                    throw new \Exception('Failed to refresh YouTube token.');
                }
            } else {
                throw new \Exception('YouTube token expired and no refresh token available.');
            }
        }
    }

    /**
     * Get the authenticated user's live broadcasts.
     */
    public function getMyLiveBroadcasts(): array
    {
        $this->authenticateWithDbToken();
        
        $response = $this->youtube->liveBroadcasts->listLiveBroadcasts('snippet,contentDetails,status', [
            'broadcastStatus' => 'all', // active, upcoming, completed
            'mine' => true,
            'maxResults' => 50
        ]);

        $broadcasts = [];
        foreach ($response->items as $item) {
            $snippet = $item->getSnippet();
            $status = $item->getStatus();
            $broadcasts[] = [
                'id' => $item->id,
                'title' => $snippet->title,
                'description' => $snippet->description,
                'scheduledStartTime' => $snippet->scheduledStartTime,
                'actualStartTime' => $snippet->actualStartTime,
                'status' => $status->lifeCycleStatus,
                'recordingStatus' => $status->recordingStatus,
                'thumbnail_url' => $this->getBestThumbnail($snippet->thumbnails)
            ];
        }

        return $broadcasts;
    }

    /**
     * Search for an active live stream in a specific channel.
     *
     * @param string $channelId
     * @return array|null
     */
    public function getLiveVideoFromChannel(string $channelId): ?array
    {
        try {
            $response = $this->youtube->search->listSearch('snippet', [
                'channelId' => $channelId,
                'eventType' => 'live',
                'type' => 'video',
                'maxResults' => 1
            ]);

            if (empty($response->items)) {
                return null;
            }

            $videoId = $response->items[0]->id->videoId;
            return $this->getVideoDetails($videoId);

        } catch (\Exception $e) {
            Log::error("YouTube API Search Error for Channel [{$channelId}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get video details from YouTube Data API.
     *
     * @param string $videoId
     * @return array|null
     */
    public function getVideoDetails(string $videoId): ?array
    {
        try {
            $response = $this->youtube->videos->listVideos('snippet,liveStreamingDetails,statistics', [
                'id' => $videoId
            ]);

            if (empty($response->items)) {
                return null;
            }

            $item = $response->items[0];
            $snippet = $item->getSnippet();
            $liveDetails = $item->getLiveStreamingDetails();
            $statistics = $item->getStatistics();

            $status = 'upcoming';
            if ($snippet->liveBroadcastContent === 'live') {
                $status = 'live';
            } elseif ($snippet->liveBroadcastContent === 'none') {
                $status = 'completed';
            }

            return [
                'youtube_id' => $videoId,
                'title' => $snippet->title,
                'description' => $snippet->description,
                'channelId' => $snippet->channelId,
                'channel_name' => $snippet->channelTitle,
                'thumbnail_url' => $this->getBestThumbnail($snippet->thumbnails),
                'status' => $status,
                'scheduled_start_time' => $liveDetails ? $liveDetails->scheduledStartTime : null,
                'actual_start_time' => $liveDetails ? $liveDetails->actualStartTime : null,
                'actual_end_time' => $liveDetails ? $liveDetails->actualEndTime : null,
                'view_count' => $statistics ? $statistics->viewCount : 0,
                'likeCount' => $statistics ? $statistics->likeCount : 0,
                'concurrentViewers' => $liveDetails ? $liveDetails->concurrentViewers : null,
                'activeLiveChatId' => $liveDetails ? $liveDetails->activeLiveChatId : null,
            ];

        } catch (\Exception $e) {
            Log::error("YouTube API Error for ID [{$videoId}]: " . $e->getMessage());

            $isChannel = str_starts_with($videoId, 'UC');
            
            // Fallback for when API fails (e.g. referer restrictions)
            return [
                'youtube_id' => $videoId,
                'title' => 'Live Stream ' . $videoId,
                'description' => 'Details unavailable: ' . $e->getMessage(),
                'channel_name' => 'Unknown Channel',
                'thumbnail_url' => $isChannel 
                    ? "/images/default-avatar.png" 
                    : "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
                'status' => 'upcoming',
                'scheduled_start_time' => now(),
                'view_count' => 0,
                'likeCount' => 0,
                'concurrentViewers' => null,
                'activeLiveChatId' => null,
            ];
        }
    }

    /**
     * Get channel details from YouTube Data API.
     *
     * @param string $channelId
     * @return array|null
     */
    public function getChannelDetails(string $channelId): ?array
    {
        try {
            $response = $this->youtube->channels->listChannels('snippet,statistics', [
                'id' => $channelId
            ]);

            if (empty($response->items)) {
                return null;
            }

            $item = $response->items[0];
            $snippet = $item->getSnippet();
            $statistics = $item->getStatistics();

            return [
                'title' => $snippet->title,
                'description' => $snippet->description,
                'customUrl' => $snippet->customUrl,
                'publishedAt' => $snippet->publishedAt,
                'thumbnails' => [
                    'default' => ['url' => $snippet->thumbnails->default->url ?? ''],
                    'medium' => ['url' => $snippet->thumbnails->medium->url ?? ''],
                    'high' => ['url' => $snippet->thumbnails->high->url ?? ''],
                ],
                'statistics' => [
                    'viewCount' => $statistics->viewCount,
                    'subscriberCount' => $statistics->subscriberCount,
                    'hiddenSubscriberCount' => $statistics->hiddenSubscriberCount,
                    'videoCount' => $statistics->videoCount,
                ],
            ];
        } catch (\Exception $e) {
            Log::error("YouTube API Channel Error for ID [{$channelId}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get live chat messages from YouTube Data API.
     *
     * @param string $liveChatId
     * @param string|null $pageToken
     * @return array|null
     */
    public function getLiveChatMessages(string $liveChatId, ?string $pageToken = null): ?array
    {
        try {
            $params = [
                'liveChatId' => $liveChatId,
                'maxResults' => 100
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->youtube->liveChatMessages->listLiveChatMessages($liveChatId, 'snippet,authorDetails', $params);

            $messages = [];
            foreach ($response->items as $item) {
                $snippet = $item->getSnippet();
                $author = $item->getAuthorDetails();

                $messages[] = [
                    'id' => $item->id,
                    'authorDetails' => [
                        'channelId' => $author->channelId,
                        'channelUrl' => $author->channelUrl,
                        'displayName' => $author->displayName,
                        'profileImageUrl' => $author->profileImageUrl,
                        'isVerified' => $author->isVerified,
                        'isChatOwner' => $author->isChatOwner,
                        'isChatSponsor' => $author->isChatSponsor,
                        'isChatModerator' => $author->isChatModerator,
                    ],
                    'snippet' => [
                        'type' => $snippet->type,
                        'hasDisplayContent' => $snippet->hasDisplayContent,
                        'displayMessage' => $snippet->displayMessage,
                        'publishedAt' => $snippet->publishedAt,
                    ],
                ];
            }

            return [
                'messages' => $messages,
                'nextPageToken' => $response->nextPageToken,
                'pollingIntervalMillis' => $response->pollingIntervalMillis,
            ];
        } catch (\Exception $e) {
            Log::error("YouTube API Live Chat Error for ID [{$liveChatId}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the best available thumbnail URL.
     */
    protected function getBestThumbnail($thumbnails)
    {
        if (isset($thumbnails->maxres)) {
            return $thumbnails->maxres->url;
        }
        if (isset($thumbnails->standard)) {
            return $thumbnails->standard->url;
        }
        if (isset($thumbnails->high)) {
            return $thumbnails->high->url;
        }
        if (isset($thumbnails->medium)) {
            return $thumbnails->medium->url;
        }
        return $thumbnails->default->url ?? null;
    }

    /**
     * Parse YouTube URL to extract video or channel ID.
     */
    public function parseIdFromUrl(string $input): string
    {
        $input = trim($input);

        // If it's already a likely ID, return it
        // Video ID: 11 chars
        // Channel ID: UC + 22 chars = 24 chars
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input) || preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $input)) {
            return $input;
        }

        // 1. Check for Video ID in standard URL (watch?v=...)
        if (preg_match('/v=([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        // 2. Check for Video ID in shortened URL (youtu.be/...)
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        // 3. Check for Video ID in embed or v/ URL
        if (preg_match('/\/(?:embed|v|shorts)\/([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        // 4. Check for Channel ID (channel/UC...)
        if (preg_match('/channel\/(UC[a-zA-Z0-9_-]{22})/', $input, $matches)) {
            return $matches[1];
        }

        return $input;
    }
}
