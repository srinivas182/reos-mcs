<?php

declare(strict_types=1);

use App\StateRules\RuleCatalogue;
use App\StateRules\TexasRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * State rules (A5.2, ADR 0019).
 *
 * - `state_rules`: each regulator rule as data, with its source, the date it
 *   takes effect and a version. Publishing a new version never changes the
 *   old one; the rule in force on a date is the newest published version
 *   whose dates contain it. Texas is seeded from TREC.
 * - `ce_completions`: CE a person has completed (REOS courses, other
 *   providers, a commission meeting). The CE card subtracts them from the
 *   requirement; the CE wallet adds uploads in Sprint 11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('state_code', 2);
            $table->foreign('state_code')->references('code')->on('states')->cascadeOnDelete();
            $table->string('topic', 20);
            $table->string('key', 60);
            $table->string('type', 20);
            $table->json('value');
            // Where it comes from: "22 TAC §535.92(a)" and a link.
            $table->string('citation', 200);
            $table->string('source_url', 500)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->date('checked_on')->nullable();
            $table->unsignedInteger('version');
            // draft, published or retired.
            $table->string('status', 12);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['state_code', 'key', 'version']);
            $table->index(['state_code', 'status', 'effective_from']);
        });

        Schema::create('ce_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('state_code', 2);
            $table->foreign('state_code')->references('code')->on('states')->cascadeOnDelete();
            $table->string('title', 200);
            // The mandatory course it counts as (legal_update_1, contracts…), or null for an elective.
            $table->string('course_code', 60)->nullable();
            $table->decimal('hours', 5, 1);
            $table->string('provider', 160)->nullable();
            // reos, other_provider or commission_meeting.
            $table->string('source', 30)->default('reos');
            $table->date('completed_on');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'state_code', 'completed_on']);
        });

        $now = now();

        foreach (TexasRules::all() as $rule) {
            $definition = RuleCatalogue::find($rule['key']);

            if ($definition === null) {
                continue;
            }

            DB::table('state_rules')->insertOrIgnore([
                'state_code' => 'TX',
                'topic' => $definition['topic']->value,
                'key' => $rule['key'],
                'type' => $definition['type']->value,
                'value' => json_encode($rule['value'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'citation' => $rule['citation'],
                'source_url' => $rule['source_url'],
                'effective_from' => $rule['effective_from'],
                'checked_on' => TexasRules::CHECKED_ON,
                'version' => 1,
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ce_completions');
        Schema::dropIfExists('state_rules');
    }
};
