<?php

declare(strict_types=1);

namespace App\VirtualBroker;

use App\Models\StateRule;

/**
 * Policy manual passages that contradict a TREC rule (A12.1, ADR 0047;
 * broker journey screen 5). Each check is a plain pattern for one rule,
 * so the broker sees exactly which sentence and which rule; the TREC rule
 * wins in every answer on that topic.
 *
 * - **Trust money** (22 TAC §535.146): deposited later than the second working day.
 * - **Compensation** (22 TAC §535.3): agents paid directly, not through the broker.
 * - **Broker name** (22 TAC §535.155): the broker's name optional in ads.
 * - **Forms** (22 TAC §537.11): agents drafting their own contract clauses.
 *
 * @phpstan-type Conflict array{rule: string, citation: string, url: string|null, page: int|null, position: int, excerpt: string, rule_text: string}
 */
final class TexasConflicts
{
    private const NUMBERS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10];

    /**
     * @param  list<array{page: int|null, position: int, content: string}>  $passages
     * @param  array<string, StateRule>  $rules  the rules in force, by key
     * @return list<Conflict>
     */
    public static function find(array $passages, array $rules): array
    {
        $conflicts = [];

        foreach ($passages as $passage) {
            foreach (preg_split('/(?<=[.!?])\s+/u', $passage['content']) ?: [] as $sentence) {
                $key = self::check($sentence);
                $rule = $key === null ? null : ($rules[$key] ?? null);

                if ($rule instanceof StateRule) {
                    $conflicts[$key.'-'.$passage['position']] = [
                        'rule' => $rule->key, 'citation' => $rule->citation, 'url' => $rule->source_url, 'page' => $passage['page'],
                        'position' => $passage['position'], 'excerpt' => PolicySearch::excerpt($sentence, 300), 'rule_text' => PolicySearch::ruleText($rule),
                    ];
                }
            }
        }

        return array_values($conflicts);
    }

    /** The rule a sentence contradicts, if any. */
    public static function check(string $sentence): ?string
    {
        $s = mb_strtolower($sentence);

        if (preg_match('/(earnest money|option fee|trust money|escrow deposit|deposit)[^.]{0,160}?\b(within|by|no later than|up to)\b[^.]{0,20}?\b(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+(business |working |calendar )?days?\b/u', $s, $m) === 1) {
            $days = is_numeric($m[3]) ? (int) $m[3] : self::NUMBERS[$m[3]];

            if ($days > 2) {
                return 'brokerage.trust_money';
            }
        }

        if (preg_match('/\b(agents?|sales agents?|associates?)\b[^.]{0,80}\b(may|can)\b[^.]{0,30}\b(accept|receive|collect|be paid)\b[^.]{0,60}\bdirectly\b/u', $s) === 1) {
            return 'brokerage.compensation';
        }

        if (preg_match('/\b(broker(age)?[\'’]?s? name|brokerage name|name of the broker(age)?)\b[^.]{0,60}\b(optional|not required|may be omitted|can be left off|is not needed|can be omitted)\b/u', $s) === 1) {
            return 'advertising.broker_name_size';
        }

        if (preg_match('/\b(agents?|you)\b[^.]{0,40}\b(may|can)\b[^.]{0,20}\b(draft|write)\b[^.]{0,40}\b(own|custom|special)\b[^.]{0,20}\b(clauses?|contracts?|addend(a|um))\b/u', $s) === 1) {
            return 'forms.promulgated';
        }

        return null;
    }
}
