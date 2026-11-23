<?php

declare(strict_types=1);

use App\StateRules\RuleCatalogue;
use App\StateRules\TexasRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CE wallet (A11.2, ADR 0045): which completions TREC's record
 * confirms, the REOS course behind a REOS completion (for reporting, A11.3),
 * the renewal reminders sent, and each renewal's checklist.
 */
return new class extends Migration
{
    private const KEYS = ['ce.renewal_steps'];

    public function up(): void
    {
        Schema::table('ce_completions', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->after('course_code')->constrained()->nullOnDelete();
            // When TREC's record showed the CE requirement met with this completion in the period.
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_by', 40)->nullable();
        });

        Schema::create('ce_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licence_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            // days_60, days_30, days_7 or ready.
            $table->string('kind', 12);
            $table->timestamp('sent_at');
            $table->unique(['licence_id', 'due_on', 'kind']);
        });

        Schema::create('licence_renewals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licence_id')->constrained()->cascadeOnDelete();
            // The expiry this renewal is for.
            $table->date('due_on');
            // The agent says they renewed with the regulator.
            $table->timestamp('renewed_at')->nullable();
            // REOS saw the new expiry on the regulator's record.
            $table->timestamp('confirmed_at')->nullable();
            $table->date('renewed_until')->nullable();
            $table->timestamps();
            $table->unique(['licence_id', 'due_on']);
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
        Schema::dropIfExists('licence_renewals');
        Schema::dropIfExists('ce_reminders');
        Schema::table('ce_completions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_id');
            $table->dropColumn(['confirmed_at', 'confirmed_by']);
        });
    }
};
