<?php

declare(strict_types=1);

use App\StateRules\RuleCatalogue;
use App\StateRules\TexasRules;
use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The virtual broker (A12.1, ADR 0047):
 *
 * - `policy_manuals`: every version of a brokerage's policy manual, with
 *   its page count, the topics found and the passages that contradict a
 *   TREC rule;
 * - `policy_passages`: what answers come from: the current manual's
 *   passages (with page and heading) and the brokerage FAQ, built from the
 *   broker's answers;
 * - `broker_questions`: an agent's question, the answer with its sources
 *   and confidence, and, when it went to the broker, why, by when, and the
 *   broker's reply;
 * - `organisations.virtual_broker`: who answers escalations and how fast.
 *
 * It also adds the Texas brokerage and forms rules the virtual broker cites.
 */
return new class extends Migration
{
    private const KEYS = ['brokerage.trust_money', 'brokerage.compensation', 'brokerage.supervision', 'brokerage.iabs', 'forms.promulgated'];

    public function up(): void
    {
        Schema::create('policy_manuals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_name', 200);
            $table->string('path', 300);
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedInteger('passages')->default(0);
            // [code, …] from PolicyKnowledge::TOPICS.
            $table->json('topics');
            // [{rule, citation, url, page, excerpt, rule_text}]: TREC wins.
            $table->json('conflicts');
            // current or replaced: the newest upload is current; every version is kept.
            $table->string('status', 10);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'version']);
        });

        Schema::create('policy_passages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            // The manual version it came from (manual passages), or null for the FAQ.
            $table->foreignId('policy_manual_id')->nullable()->constrained()->cascadeOnDelete();
            // manual or faq.
            $table->string('kind', 8);
            $table->string('heading', 300)->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->text('content');
            // The broker's answer this FAQ entry came from.
            $table->unsignedBigInteger('broker_question_id')->nullable();
            $table->timestamps();
            // A removed FAQ entry can be put back (Undo).
            $table->softDeletes();
            $table->index(['organisation_id', 'kind']);
        });

        Schema::create('broker_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->string('language', 5)->default('en');
            // The virtual broker's answer (for an escalated question, the broker's draft).
            $table->text('answer');
            // high, medium or low.
            $table->string('confidence', 6);
            // [{kind: manual|faq|rule, title, citation, page, url, effective_from, excerpt}]
            $table->json('sources');
            // answered (by the virtual broker), waiting (with the broker), confirmed or corrected (by the broker).
            $table->string('status', 10);
            // Why it went to the broker: unclear, money, legal or asked.
            $table->string('reason', 8)->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->text('broker_answer')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->boolean('added_to_knowledge')->default(false);
            $table->string('engine', 10)->default('simulator');
            $table->timestamps();
            $table->index(['organisation_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('organisations', function (Blueprint $table): void {
            // {answerer_id: int|null, reply_hours: int}
            $table->json('virtual_broker')->nullable();
        });

        RowLevelSecurity::protect('policy_manuals');
        RowLevelSecurity::protect('policy_passages');
        RowLevelSecurity::protect('broker_questions');

        foreach (TexasRules::all() as $rule) {
            $definition = RuleCatalogue::find($rule['key']);

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
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn('virtual_broker');
        });
        Schema::dropIfExists('broker_questions');
        Schema::dropIfExists('policy_passages');
        Schema::dropIfExists('policy_manuals');
    }
};
