<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            if (!Schema::hasColumn('agendas', 'ticket_quota_label')) {
                $table->string('ticket_quota_label')->nullable()->after('ticket_quota');
            }
            if (!Schema::hasColumn('agendas', 'ticket_info_title')) {
                $table->string('ticket_info_title')->default('Informasi Tiket')->after('ticket_quota_label');
            }
            if (!Schema::hasColumn('agendas', 'organizer_logo')) {
                $table->string('organizer_logo')->nullable()->after('organizer');
            }
            if (!Schema::hasColumn('agendas', 'organizer_verified')) {
                $table->boolean('organizer_verified')->default(true)->after('organizer_logo');
            }
            if (!Schema::hasColumn('agendas', 'registration_enabled')) {
                $table->boolean('registration_enabled')->default(false)->after('organizer_verified');
            }
            if (!Schema::hasColumn('agendas', 'registration_url')) {
                $table->string('registration_url')->nullable()->after('registration_enabled');
            }
            if (!Schema::hasColumn('agendas', 'registration_button_text')) {
                $table->string('registration_button_text')->default('Daftar Sekarang')->after('registration_url');
            }
            if (!Schema::hasColumn('agendas', 'registration_note')) {
                $table->string('registration_note')->nullable()->after('registration_button_text');
            }
            if (!Schema::hasColumn('agendas', 'registration_closed_text')) {
                $table->string('registration_closed_text')->nullable()->after('registration_note');
            }
            if (!Schema::hasColumn('agendas', 'registration_open_until')) {
                $table->timestamp('registration_open_until')->nullable()->after('registration_closed_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $columns = [
                'ticket_quota_label',
                'ticket_info_title',
                'organizer_logo',
                'organizer_verified',
                'registration_enabled',
                'registration_url',
                'registration_button_text',
                'registration_note',
                'registration_closed_text',
                'registration_open_until',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('agendas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
