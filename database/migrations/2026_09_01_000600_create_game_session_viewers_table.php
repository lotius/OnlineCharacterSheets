<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_session_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('viewer_key');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['game_session_id', 'viewer_key']);
            $table->index(['game_session_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_session_viewers');
    }
};
