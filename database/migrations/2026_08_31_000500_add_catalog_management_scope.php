<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $definitionTables = [
        'stat_definitions',
        'slot_definitions',
        'tray_definitions',
        'character_class_definitions',
        'item_definitions',
        'spell_definitions',
        'skill_definitions',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin')->default(false)->after('password');
            });
        }

        foreach ($this->definitionTables as $tableName) {
            $versionIndex = "{$tableName}_version_index";
            $oldUnique = "{$tableName}_game_system_version_id_key_unique";
            $automaticScopedUnique = "{$tableName}_game_system_version_id_scope_key_key_unique";
            $scopedUnique = "{$tableName}_scope_unique";

            if (! $this->hasIndex($tableName, $versionIndex)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->index('game_system_version_id', $versionIndex));
            }
            if ($this->hasIndex($tableName, $oldUnique)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($oldUnique));
            }
            if (! Schema::hasColumn($tableName, 'owner_user_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('owner_user_id')->nullable()->after('game_system_version_id')->constrained('users')->cascadeOnDelete();
                    $table->string('scope_key')->default('system')->after('owner_user_id');
                });
            }
            if (! $this->hasIndex($tableName, $automaticScopedUnique) && ! $this->hasIndex($tableName, $scopedUnique)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->unique(['game_system_version_id', 'scope_key', 'key'], $scopedUnique));
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->definitionTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $automaticScopedUnique = "{$tableName}_game_system_version_id_scope_key_key_unique";
                $scopedUnique = "{$tableName}_scope_unique";
                $table->dropUnique($this->hasIndex($tableName, $automaticScopedUnique) ? $automaticScopedUnique : $scopedUnique);
                $table->dropConstrainedForeignId('owner_user_id');
                $table->dropColumn('scope_key');
                $table->unique(['game_system_version_id', 'key']);
                $table->dropIndex("{$tableName}_version_index");
            });
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['name'] === $name);
    }
};
