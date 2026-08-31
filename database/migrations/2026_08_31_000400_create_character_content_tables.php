<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['game_system_version_id', 'key']);
        });

        Schema::create('character_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_definition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_name')->nullable();
            $table->text('custom_description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('state')->nullable();
            $table->timestamps();
            $table->index(['character_id', 'item_definition_id']);
        });

        Schema::create('character_item_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['character_item_id', 'slot_definition_id']);
        });

        Schema::create('spell_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['game_system_version_id', 'key']);
        });

        Schema::create('character_spells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spell_definition_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_used')->default(false);
            $table->unsignedInteger('charges')->nullable();
            $table->json('state')->nullable();
            $table->timestamps();
            $table->unique(['character_id', 'spell_definition_id']);
        });

        Schema::create('skill_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['game_system_version_id', 'key']);
        });

        Schema::create('character_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_definition_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_used')->default(false);
            $table->unsignedInteger('charges')->nullable();
            $table->json('state')->nullable();
            $table->timestamps();
            $table->unique(['character_id', 'skill_definition_id']);
        });

        Schema::create('character_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->longText('content')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->unique('character_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_notes');
        Schema::dropIfExists('character_skills');
        Schema::dropIfExists('skill_definitions');
        Schema::dropIfExists('character_spells');
        Schema::dropIfExists('spell_definitions');
        Schema::dropIfExists('character_item_slots');
        Schema::dropIfExists('character_items');
        Schema::dropIfExists('item_definitions');
    }
};
