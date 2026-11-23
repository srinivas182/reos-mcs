<?php

declare(strict_types=1);

namespace App\Practice;

use Illuminate\Support\Facades\DB;

/**
 * REOS's Texas diagnostic questions (A8.3, decision 4), from
 * `database/data/diagnostic_tx.php`. Loaded by a migration on every
 * deployment and updated in place by key when the file changes.
 */
final class DiagnosticBank
{
    /** The exam outline's share of the diagnostic by topic (and of readiness). */
    public const WEIGHTS = ['agency' => 9, 'contracts' => 9, 'ownership' => 8, 'finance' => 7, 'math' => 7, 'state_rules' => 10];

    public const QUESTIONS = 50;

    public const MINUTES = 45;

    /** @return list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: array{0: string, 1: list<string>, 2: string}, 6: array{0: string, 1: list<string>, 2: string}}> */
    public static function questions(): array
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: array{0: string, 1: list<string>, 2: string}, 6: array{0: string, 1: list<string>, 2: string}}> $questions */
        $questions = require database_path('data/diagnostic_tx.php');

        return $questions;
    }

    public static function load(): void
    {
        $now = now();

        foreach (self::questions() as [$key, $topic, $portion, $correct, $source, $en, $es]) {
            DB::table('quiz_questions')->updateOrInsert(['key' => 'diagnostic-tx:'.$key], [
                'state_code' => 'TX',
                'topic' => $topic,
                'portion' => $portion,
                'difficulty' => 'medium',
                'content' => json_encode([
                    'en' => ['question' => $en[0], 'options' => $en[1], 'explanation' => $en[2]],
                    'es' => ['question' => $es[0], 'options' => $es[1], 'explanation' => $es[2]],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'correct_option' => $correct,
                'source' => $source,
                'is_sample' => false,
                'in_free_quiz' => false,
                'in_diagnostic' => true,
                'organisation_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
