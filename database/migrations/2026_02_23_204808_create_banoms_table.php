<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banoms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('short_desc')->nullable();
            $table->text('long_desc')->nullable();
            $table->text('history')->nullable();
            $table->text('vision')->nullable();
            $table->json('mission')->nullable(); // Array of strings
            $table->json('management')->nullable(); // Array of objects {name, position, image}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banoms');
    }
};
