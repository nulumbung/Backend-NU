<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(); // Title from YouTube
            $table->string('youtube_id')->unique(); // YouTube Video ID (e.g., Kz3FK5FbBz8)
            $table->string('channel_name')->nullable(); // Channel Name
            $table->string('thumbnail_url')->nullable(); // Thumbnail URL
            $table->boolean('is_active')->default(false); // Only one active stream at a time usually
            $table->timestamp('scheduled_start_time')->nullable(); // For upcoming streams
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};
