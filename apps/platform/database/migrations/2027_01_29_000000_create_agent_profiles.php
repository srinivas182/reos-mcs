<?php

declare(strict_types=1);

use App\StateRules\RuleCatalogue;
use App\StateRules\TexasRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agent modes (A11.1a, ADR 0044): one Agent membership, four goals (Launch,
 * Grow, Lead, Own) that decide what the home screen shows first, work-style
 * flags, and the goal's history. The switch thresholds are state rules.
 */
return new class extends Migration
{
    private const KEYS = ['agents.grow_closings', 'agents.lead_closings', 'agents.own_years'];

    public function up(): void
    {
        Schema::create('agent_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // launch, grow, lead or own; null until the agent picks one.
            $table->string('goal', 8)->nullable();
            // part_time, bilingual, specialist, referral_only, returning.
            $table->jsonb('flags')->default('[]');
            $table->timestamp('welcomed_at')->nullable();
            // A suggested switch the agent said "Not yet" to, until this date.
            $table->string('snoozed_goal', 8)->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_goal_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('from_goal', 8)->nullable();
            $table->string('to_goal', 8);
            // setup, manual or suggested.
            $table->string('reason', 10);
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
        });

        $catalogue = RuleCatalogue::all();

        foreach (TexasRules::all() as $rule) {
            $definition = $catalogue[$rule['key']] ?? null;

            if (! in_array($rule['key'], self::KEYS, true) || $definition === null || ! DB::table('states')->where('code', 'TX')->exists()) {
                continue;
            }

            if (! DB::table('state_rules')->where('state_code', 'TX')->where('key', $rule['key'])->exists()) {
                DB::table('state_rules')->insert([
                    'state_code' => 'TX', 'topic' => $definition['topic']->value, 'key' => $rule['key'], 'type' => $definition['type']->value,
                    'value' => json_encode($rule['value'], JSON_THROW_ON_ERROR), 'citation' => $rule['citation'], 'source_url' => $rule['source_url'],
                    'effective_from' => $rule['effective_from'], 'checked_on' => '2026-11-24', 'version' => 1, 'status' => 'published',
                    'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('state_rules')->whereIn('key', self::KEYS)->delete();
        Schema::dropIfExists('agent_goal_changes');
        Schema::dropIfExists('agent_profiles');
    }
};
