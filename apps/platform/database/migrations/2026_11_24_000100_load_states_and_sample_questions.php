<?php

declare(strict_types=1);

use App\Onboarding\SampleQuestions;
use App\Onboarding\StateCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data every deployment needs: the states (Texas live) and the
 * free quiz's sample questions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (StateCatalogue::all() as $state) {
            DB::table('states')->insertOrIgnore([
                'code' => $state['code'],
                'name' => $state['name'],
                'status' => $state['status']->value,
                'regulator' => $state['regulator'] ?? null,
                'exam_vendor' => $state['exam_vendor'] ?? null,
                'qualifying_hours' => $state['qualifying_hours'] ?? null,
                'ce_hours' => $state['ce_hours'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (SampleQuestions::all() as $question) {
            DB::table('quiz_questions')->insert([
                'state_code' => $question['state_code'],
                'topic' => $question['topic']->value,
                'content' => json_encode($question['content'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'correct_option' => $question['correct_option'],
                'source' => $question['source'],
                'is_sample' => true,
                'in_free_quiz' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('quiz_questions')->where('is_sample', true)->delete();
        DB::table('states')->delete();
    }
};
