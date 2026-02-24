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
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_provider', 50)->default('email')->after('avatar');
            $table->string('provider_id')->nullable()->after('auth_provider');
            $table->timestamp('last_login_at')->nullable()->after('provider_id');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->index(['auth_provider', 'provider_id'], 'users_auth_provider_provider_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_auth_provider_provider_id_index');
            $table->dropColumn(['auth_provider', 'provider_id', 'last_login_at', 'last_login_ip']);
        });
    }
};
