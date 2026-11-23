<?php

declare(strict_types=1);

use App\StateRules\RuleCatalogue;
use App\StateRules\TexasRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The licence journey (A10.3a, ADR 0041; candidate screens 16, 15, 14 and
 * 19): each candidate's check-offs of the state's steps, the application
 * date (the one-year window), the booked exam, the exam-day checklist, and
 * each exam attempt per portion. Texas's steps, application window,
 * attempts and exam-day rules are seeded as state rules (screen 62) for
 * databases made before they existed.
 */
return new class extends Migration
{
    private const KEYS = ['licensing.steps', 'licensing.application_months', 'exam.attempts', 'exam.day_rules'];

    public function up(): void
    {
        Schema::create('licence_journeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('state_code', 2);
            // {"education": "2026-10-02", ...}: the candidate's own check-offs and dates.
            $table->json('steps');
            $table->date('application_filed_on')->nullable();
            $table->date('exam_on')->nullable();
            $table->string('exam_time', 5)->nullable();
            $table->string('test_centre', 160)->nullable();
            // Exam-day checklist items ticked: id, confirmation, final_simulation, flashcards, sleep.
            $table->json('kit');
            // [{"portion": "national", "passed": true, "on": "2027-01-20"}, ...]
            $table->json('attempts');
            // Weak areas from the score report, for the retake plan only.
            $table->json('weak_topics');
            // Reminders sent, so each goes once: "week:2027-01-20", "day:…", "result:…".
            $table->json('reminded');
            $table->timestamps();
        });

        foreach (TexasRules::all() as $rule) {
            $definition = RuleCatalogue::find($rule['key']);

            if (! in_array($rule['key'], self::KEYS, true) || $definition === null || ! DB::table('states')->where('code', 'TX')->exists()) {
                continue;
            }

            if (! DB::table('state_rules')->where('state_code', 'TX')->where('key', $rule['key'])->exists()) {
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
        DB::table('state_rules')->whereIn('key', self::KEYS)->delete();
        Schema::dropIfExists('licence_journeys');
    }
};
