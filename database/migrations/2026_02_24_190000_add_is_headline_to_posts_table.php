<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('posts', 'is_headline')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->boolean('is_headline')->default(false)->after('is_breaking');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'is_headline')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('is_headline');
            });
        }
    }
};
