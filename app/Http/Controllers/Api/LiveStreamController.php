<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLiveStreamRequest;
use App\Http\Requests\UpdateLiveStreamRequest;
use App\Http\Resources\LiveStreamResource;
use App\Models\LiveStream;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiveStreamController extends Controller
{
    protected $youTubeService;

    public function __construct(YouTubeService $youTubeService)
    {
        $this->youTubeService = $youTubeService;
    }

    /**
     * Get authenticated user's YouTube live broadcasts.
     */
    public function myBroadcasts(): JsonResponse
    {
        try {
            $broadcasts = $this->youTubeService->getMyLiveBroadcasts();
            return response()->json(['data' => $broadcasts]);
        } catch (\Exception $e) {
            Log::error('fetch myBroadcasts error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengambil daftar siaran YouTube: ' . $e->getMessage()], 401);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $streams = LiveStream::orderBy('created_at', 'desc')->paginate(10);
            return response()->json(LiveStreamResource::collection($streams)->response()->getData(true));
        } catch (\Exception $e) {
            Log::error('LiveStreamController index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Gagal mengambil daftar siaran: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the currently active live stream for the frontend.
     */
    public function getActive(): JsonResponse
    {
        // 1. Try to find an explicitly active stream
        $stream = LiveStream::active()->first();

        // 2. If none active, find the nearest upcoming stream
        if (!$stream) {
            $stream = LiveStream::upcoming()->first();
        }

        // 3. If still nothing, return 404
        if (!$stream) {
            return response()->json(['message' => 'No active or upcoming live stream found.'], 404);
        }

        // Optional: Refresh data from YouTube if it's been a while (e.g. status check)
        // For now, we'll trust the database or a background job would update it.

        return response()->json(new LiveStreamResource($stream));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLiveStreamRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Fetch details from YouTube
            $details = $this->youTubeService->getVideoDetails($validated['youtube_id']);

            // Merge details with validated data
            $data = array_merge($details ?? [], $validated);

            if (!$details) {
                Log::warning('YouTube API returned no details for ID: ' . $validated['youtube_id']);
                $data['title'] = $data['title'] ?? 'Live Stream ' . $validated['youtube_id'];
                $data['channel_name'] = $data['channel_name'] ?? 'Unknown Channel';
                $data['thumbnail_url'] = $data['thumbnail_url'] ?? "https://img.youtube.com/vi/{$validated['youtube_id']}/hqdefault.jpg";
                $data['status'] = $data['status'] ?? 'upcoming';
            }

            if (!empty($data['is_active']) && $data['is_active']) {
                LiveStream::where('is_active', true)->update(['is_active' => false]);
            }

            $stream = LiveStream::create($data);

            return response()->json(new LiveStreamResource($stream), 201);
        } catch (\Exception $e) {
            Log::error('LiveStreamController store error: ' . $e->getMessage(), [
                'youtube_id' => $request->input('youtube_id'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Gagal menyimpan siaran: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(LiveStream $liveStream): LiveStreamResource
    {
        return new LiveStreamResource($liveStream);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLiveStreamRequest $request, LiveStream $liveStream): JsonResponse
    {
        $validated = $request->validated();

        // If youtube_id changed, maybe refresh details? 
        // For now, assume user updates what they want manually or we rely on scheduled jobs.
        
        if (!empty($validated['is_active']) && $validated['is_active']) {
            LiveStream::where('id', '!=', $liveStream->id)->where('is_active', true)->update(['is_active' => false]);
        }

        $liveStream->update($validated);

        return response()->json(new LiveStreamResource($liveStream));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LiveStream $liveStream): JsonResponse
    {
        $liveStream->delete();
        return response()->json(['message' => 'Live stream deleted successfully.']);
    }

    /**
     * Force refresh data from YouTube for a specific stream.
     */
    public function refresh(LiveStream $liveStream): JsonResponse
    {
        $details = $this->youTubeService->getVideoDetails($liveStream->youtube_id);
        
        if ($details) {
            $liveStream->update($details);
            return response()->json(new LiveStreamResource($liveStream));
        }

        return response()->json(['message' => 'Failed to refresh data from YouTube.'], 500);
    }

    /**
     * Find an active live stream by Channel ID.
     */
    public function findActiveByChannel(string $channelId): JsonResponse
    {
        try {
            // Parse the input in case it's a full URL
            $channelId = $this->youTubeService->parseIdFromUrl($channelId);

            $details = $this->youTubeService->getLiveVideoFromChannel($channelId);
            
            if (!$details) {
                // Return 200 OK instead of 404, with channel info payload for frontend
                $channelDetails = $this->youTubeService->getChannelDetails($channelId);
                
                return response()->json([
                    'message' => 'Tidak ada siaran aktif. Channel disimpan untuk dipantau otomatis.',
                    'youtube_id' => $channelId,
                    'title' => ($channelDetails ? $channelDetails['title'] : 'Auto Live') . ' (Menunggu Live)',
                    'channel_name' => $channelDetails ? $channelDetails['title'] : 'Unknown Channel',
                    'thumbnail_url' => $channelDetails ? ($channelDetails['thumbnails']['high']['url'] ?? null) : null,
                    'status' => 'upcoming',
                    'is_channel_fallback' => true
                ], 200);
            }

            return response()->json($details);
        } catch (\Exception $e) {
            Log::error('LiveStreamController findActiveByChannel error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mencari siaran: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Proxy to get YouTube video details.
     */
    public function proxyVideoDetails(string $videoId): JsonResponse
    {
        // Parse the input in case it's a full URL
        $videoId = $this->youTubeService->parseIdFromUrl($videoId);

        // If the ID starts with UC, it's a Channel ID. 
        // We should search for an active live video in this channel.
        if (str_starts_with($videoId, 'UC')) {
            $details = $this->youTubeService->getLiveVideoFromChannel($videoId);
            if (!$details) {
                return response()->json([
                    'message' => 'Tidak ada siaran aktif di channel ini.',
                    'is_channel' => true,
                    'channel_id' => $videoId
                ], 404);
            }
            return response()->json($details);
        }

        $details = $this->youTubeService->getVideoDetails($videoId);
        if (!$details) {
            return response()->json(['message' => 'Video tidak ditemukan.'], 404);
        }
        return response()->json($details);
    }

    /**
     * Proxy to get YouTube channel details.
     */
    public function proxyChannelDetails(string $channelId): JsonResponse
    {
        $details = $this->youTubeService->getChannelDetails($channelId);
        if (!$details) {
            return response()->json(['message' => 'Channel tidak ditemukan.'], 404);
        }
        return response()->json($details);
    }

    /**
     * Proxy to get YouTube live chat messages.
     */
    public function proxyLiveChat(string $liveChatId, Request $request): JsonResponse
    {
        $pageToken = $request->query('pageToken');
        $details = $this->youTubeService->getLiveChatMessages($liveChatId, $pageToken);
        if (!$details) {
            return response()->json(['message' => 'Chat tidak ditemukan atau gagal diambil.'], 404);
        }
        return response()->json($details);
    }
}
