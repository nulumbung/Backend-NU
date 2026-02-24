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
        Schema::table('agendas', function (Blueprint $table) {
            $table->json('rundown')->nullable();
            $table->json('gallery')->nullable();
            $table->string('ticket_price')->default('Gratis');
            $table->integer('ticket_quota')->default(0);
            $table->string('organizer')->default('PBNU');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->dropColumn(['rundown', 'gallery', 'ticket_price', 'ticket_quota', 'organizer']);
        });
    }
};
