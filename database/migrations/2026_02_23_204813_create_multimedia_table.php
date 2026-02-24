<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multimedia', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('type', ['video', 'photo']);
            $table->string('thumbnail')->nullable();
            $table->string('url')->nullable(); // Youtube URL or main image
            $table->json('gallery')->nullable(); // Array of image URLs for photo album
            $table->text('description')->nullable();
            $table->dateTime('date');
            $table->string('author')->nullable();
            $table->json('tags')->nullable();
            $table->bigInteger('views')->default(0);
            $table->bigInteger('likes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multimedia');
    }
};
