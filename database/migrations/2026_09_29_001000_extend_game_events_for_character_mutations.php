<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->unsignedBigInteger('character_id')->nullable()->index();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->uuid('mutation_id')->nullable()->index();
            $table->boolean('is_undoable')->default(false);
            $table->index(['entity_type', 'entity_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id', 'id']);
            $table->dropColumn(['character_id', 'entity_type', 'entity_id', 'mutation_id', 'is_undoable']);
        });
    }
};
