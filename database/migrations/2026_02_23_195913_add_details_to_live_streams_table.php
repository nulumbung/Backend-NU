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
        Schema::table('live_streams', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->string('status')->default('upcoming')->after('is_active'); // upcoming, live, completed
            $table->timestamp('actual_start_time')->nullable()->after('scheduled_start_time');
            $table->timestamp('actual_end_time')->nullable()->after('actual_start_time');
            $table->bigInteger('view_count')->default(0)->after('thumbnail_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'status',
                'actual_start_time',
                'actual_end_time',
                'view_count',
            ]);
        });
    }
};
