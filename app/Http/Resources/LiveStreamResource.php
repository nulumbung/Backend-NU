<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveStreamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'youtube_id' => $this->youtube_id,
            'youtube_url' => 'https://www.youtube.com/watch?v=' . $this->youtube_id,
            'embed_url' => 'https://www.youtube.com/embed/' . $this->youtube_id,
            'channel_name' => $this->channel_name,
            'thumbnail_url' => $this->thumbnail_url,
            'is_active' => $this->is_active,
            'status' => $this->status,
            'view_count' => $this->view_count,
            'scheduled_start_time' => $this->scheduled_start_time,
            'actual_start_time' => $this->actual_start_time,
            'actual_end_time' => $this->actual_end_time,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
