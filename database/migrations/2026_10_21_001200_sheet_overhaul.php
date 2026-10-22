<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stat_definitions', function (Blueprint $table) {
            // resource = current/max counter (Body), attribute = single value that items can modify (Attack), currency = spendable amount (Gold).
            $table->string('kind')->default('resource')->after('data_type');
        });

        Schema::table('characters', function (Blueprint $table) {
            // Monotonic per-character version so clients can discard out-of-order responses.
            $table->unsignedBigInteger('revision')->default(0)->after('configuration');
        });

        $this->moveGoldIntoCurrencyStat();

        Schema::table('characters', fn (Blueprint $table) => $table->dropColumn('gold'));
        Schema::table('character_items', fn (Blueprint $table) => $table->dropColumn('is_active'));

        Schema::create('stream_overlays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->string('channel_key', 64)->unique();
            $table->json('configuration');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_overlays');
        Schema::table('character_items', fn (Blueprint $table) => $table->boolean('is_active')->default(false)->after('quantity'));
        Schema::table('characters', fn (Blueprint $table) => $table->unsignedInteger('gold')->default(0)->after('portrait'));

        $gold = DB::table('stat_definitions')->where('key', 'gold')->where('scope_key', 'system')->pluck('id');
        foreach (DB::table('character_stats')->whereIn('stat_definition_id', $gold)->get() as $stat) {
            DB::table('characters')->where('id', $stat->character_id)->update(['gold' => (int) json_decode($stat->value)]);
        }

        Schema::table('characters', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('stat_definitions', fn (Blueprint $table) => $table->dropColumn('kind'));
    }

    private function moveGoldIntoCurrencyStat(): void
    {
        $now = now();
        foreach (DB::table('game_system_versions')->pluck('id') as $versionId) {
            $definitionId = DB::table('stat_definitions')
                ->where('game_system_version_id', $versionId)->where('scope_key', 'system')->where('key', 'gold')->value('id');
            $definitionId ??= DB::table('stat_definitions')->insertGetId([
                'game_system_version_id' => $versionId, 'owner_user_id' => null, 'scope_key' => 'system',
                'key' => 'gold', 'name' => 'Gold', 'data_type' => 'integer', 'kind' => 'currency',
                'default_value' => json_encode(0), 'minimum_value' => 0, 'display_order' => 900,
                'is_public' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('stat_definitions')->where('id', $definitionId)->update(['kind' => 'currency']);

            foreach (DB::table('characters')->where('game_system_version_id', $versionId)->get(['id', 'gold']) as $character) {
                DB::table('character_stats')->updateOrInsert(
                    ['character_id' => $character->id, 'stat_definition_id' => $definitionId],
                    ['value' => json_encode((int) $character->gold), 'max_value' => null, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }
};
