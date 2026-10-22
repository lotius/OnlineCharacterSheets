<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->foreignId('current_character_id')->nullable()->constrained('characters')->nullOnDelete();
        });
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('revision');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->json('before_state');
            $table->json('after_state');
            $table->foreignId('undoes_event_id')->nullable()->unique()->constrained('game_events')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['game_session_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
        Schema::table('game_sessions', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_character_id'));
    }
};
