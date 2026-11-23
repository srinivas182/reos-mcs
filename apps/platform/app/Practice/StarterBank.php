<?php

declare(strict_types=1);

namespace App\Practice;

use Illuminate\Support\Facades\DB;

/**
 * REOS's Texas starter bank (A9.1, Sprint 9 decision 2): 300 questions from
 * `database/data/starter_tx.php`, 200 national and 100 Texas, so every
 * simulation can draw new questions. They load as samples, count in tests
 * and simulations (ADR 0035), and are approved, edited or retired by a
 * school admin on the question bank's Starter bank tab (screen 45).
 */
final class StarterBank
{
    public const PREFIX = 'starter-tx:';

    /** @return list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: array{0: string, 1: list<string>, 2: string}, 6: array{0: string, 1: list<string>, 2: string}}> */
    public static function questions(): array
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: array{0: string, 1: list<string>, 2: string}, 6: array{0: string, 1: list<string>, 2: string}}> $questions */
        $questions = require database_path('data/starter_tx.php');

        return $questions;
    }

    /** Adds the questions not loaded yet. One already in the bank, edited, approved or retired, is left as it is. */
    public static function load(): int
    {
        $now = now();
        $loaded = DB::table('quiz_questions')->where('key', 'like', self::PREFIX.'%')->pluck('key')->all();
        $rows = [];

        foreach (self::questions() as [$key, $topic, $portion, $correct, $source, $en, $es]) {
            if (in_array(self::PREFIX.$key, $loaded, true)) {
                continue;
            }

            $rows[] = [
                'key' => self::PREFIX.$key,
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
                'is_sample' => true,
                'in_free_quiz' => false,
                'in_diagnostic' => false,
                'organisation_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('quiz_questions')->insert($chunk);
        }

        return count($rows);
    }
}
