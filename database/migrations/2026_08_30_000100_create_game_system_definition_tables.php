<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_systems', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('default_theme')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('game_system_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('name')->nullable();
            $table->json('configuration')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['game_system_id', 'version']);
        });

        Schema::create('stat_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->string('data_type')->default('integer');
            $table->json('default_value')->nullable();
            $table->json('default_max_value')->nullable();
            $table->decimal('minimum_value', 12, 2)->nullable();
            $table->decimal('maximum_value', 12, 2)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['game_system_version_id', 'key']);
        });

        Schema::create('slot_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('icon')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedInteger('display_order')->default(0);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['game_system_version_id', 'key']);
        });

        Schema::create('tray_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('icon')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_collapsible')->default(true);
            $table->boolean('default_open')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['game_system_version_id', 'key']);
        });

        Schema::create('character_class_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_system_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('portrait_asset_id')->nullable();
            $table->json('configuration')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['game_system_version_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_class_definitions');
        Schema::dropIfExists('tray_definitions');
        Schema::dropIfExists('slot_definitions');
        Schema::dropIfExists('stat_definitions');
        Schema::dropIfExists('game_system_versions');
        Schema::dropIfExists('game_systems');
    }
};
