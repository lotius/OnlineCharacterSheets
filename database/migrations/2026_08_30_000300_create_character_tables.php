<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('character_class_definition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('portrait')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('character_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stat_definition_id')->constrained()->restrictOnDelete();
            $table->json('value');
            $table->json('max_value')->nullable();
            $table->timestamps();
            $table->unique(['character_id', 'stat_definition_id']);
        });

        Schema::create('session_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('turn_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['game_session_id', 'character_id']);
            $table->index(['game_session_id', 'assigned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_characters');
        Schema::dropIfExists('character_stats');
        Schema::dropIfExists('characters');
    }
};
