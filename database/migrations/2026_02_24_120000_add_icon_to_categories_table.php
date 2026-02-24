<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('categories', 'icon')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('icon')->nullable()->after('image');
            });
        }

        DB::table('categories')
            ->whereNull('icon')
            ->whereNotNull('image')
            ->where('image', 'like', 'Md%')
            ->update(['icon' => DB::raw('image')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'icon')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('icon');
            });
        }
    }
};
