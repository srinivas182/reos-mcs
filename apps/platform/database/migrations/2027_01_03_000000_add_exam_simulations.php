<?php

declare(strict_types=1);

use App\StateRules\RuleCatalogue;
use App\StateRules\TexasRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exam simulations (A9.1, ADR 0035): portion tests and the full simulation
 * in the real format. A simulation has timed sections (National, then the
 * state portion); pretest items aren't scored, as on the real exam; a
 * practice-mode test can be paused. Texas's exam format is seeded as state
 * rules (screen 62) for databases made before it existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_attempts', function (Blueprint $table) {
            // [{"portion": "national", "from": 0, "count": 85, "minutes": 150, "pass": 56, "scored": 80, "started_at": …, "ends_at": …, "ended_at": …}]
            $table->json('sections')->nullable()->after('flags');
            // Pretest items: asked, not scored.
            $table->json('unscored')->nullable()->after('sections');
            $table->timestamp('paused_at')->nullable()->after('ends_at');
            // {"national": {"correct": 60, "total": 80, "pass": 56, "passed": true}, ...}
            $table->json('by_portion')->nullable()->after('by_topic');
        });

        foreach (TexasRules::all() as $rule) {
            $definition = RuleCatalogue::find($rule['key']);

            if (! str_starts_with($rule['key'], 'exam.') || $rule['key'] === 'exam.vendor' || $definition === null) {
                continue;
            }

            if (! DB::table('state_rules')->where('state_code', 'TX')->where('key', $rule['key'])->exists() && DB::table('states')->where('code', 'TX')->exists()) {
                DB::table('state_rules')->insert([
                    'state_code' => 'TX', 'topic' => $definition['topic']->value, 'key' => $rule['key'], 'type' => $definition['type']->value,
                    'value' => json_encode($rule['value'], JSON_THROW_ON_ERROR), 'citation' => $rule['citation'], 'source_url' => $rule['source_url'],
                    'effective_from' => $rule['effective_from'], 'checked_on' => '2026-11-23', 'version' => 1, 'status' => 'published',
                    'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('state_rules')->where('key', 'like', 'exam.national.%')->orWhere('key', 'like', 'exam.state.%')->delete();

        Schema::table('test_attempts', function (Blueprint $table) {
            $table->dropColumn(['sections', 'unscored', 'paused_at', 'by_portion']);
        });
    }
};
