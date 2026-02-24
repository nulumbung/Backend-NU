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
        $this->client->setDeveloperKey(env('YOUTUBE_API_KEY'));
        $this->youtube = new YouTube($this->client);
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
                'channel_name' => $snippet->channelTitle,
                'thumbnail_url' => $this->getBestThumbnail($snippet->thumbnails),
                'status' => $status,
                'scheduled_start_time' => $liveDetails ? $liveDetails->scheduledStartTime : null,
                'actual_start_time' => $liveDetails ? $liveDetails->actualStartTime : null,
                'actual_end_time' => $liveDetails ? $liveDetails->actualEndTime : null,
                'view_count' => $statistics ? $statistics->viewCount : 0,
            ];

        } catch (\Exception $e) {
            Log::error('YouTube API Error: ' . $e->getMessage());
            // Fallback for when API fails (e.g. referer restrictions)
            // We return minimal info so the creation can still proceed
            return [
                'youtube_id' => $videoId,
                'title' => 'Live Stream ' . $videoId,
                'description' => 'Details unavailable due to API restriction',
                'channel_name' => 'Unknown Channel',
                'thumbnail_url' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
                'status' => 'upcoming',
                'scheduled_start_time' => now(),
                'view_count' => 0,
            ];
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
}
