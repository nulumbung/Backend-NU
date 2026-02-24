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
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $streams = LiveStream::orderBy('created_at', 'desc')->paginate(10);
        return LiveStreamResource::collection($streams);
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
        $validated = $request->validated();

        // Fetch details from YouTube
        $details = $this->youTubeService->getVideoDetails($validated['youtube_id']);

        // Merge details with validated data (validated data takes precedence if provided manually)
        $data = array_merge($details ?? [], $validated);

        // Ensure we have at least defaults if API failed and user didn't provide overrides
        if (!$details) {
             $data['title'] = $data['title'] ?? 'Live Stream ' . $validated['youtube_id'];
             $data['channel_name'] = $data['channel_name'] ?? 'Unknown Channel';
             $data['thumbnail_url'] = $data['thumbnail_url'] ?? "https://img.youtube.com/vi/{$validated['youtube_id']}/hqdefault.jpg";
             $data['status'] = $data['status'] ?? 'upcoming';
        }

        // Handle is_active logic
        if (!empty($data['is_active']) && $data['is_active']) {
            LiveStream::where('is_active', true)->update(['is_active' => false]);
        }

        $stream = LiveStream::create($data);

        return response()->json(new LiveStreamResource($stream), 201);
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
}
