<?php

declare(strict_types=1);

namespace App\Crm\Insights;

use Carbon\CarbonImmutable;

/**
 * The simulator (no Bedrock keys): a plain-rules summary and next step
 * from the same brief Claude reads, so screens and tests work the same.
 *
 * @phpstan-import-type Brief from InsightWriter
 * @phpstan-import-type Insight from InsightWriter
 */
final readonly class SimulatedInsightWriter implements InsightWriter
{
    public function engine(): string
    {
        return 'simulator';
    }

    public function write(array $brief): array
    {
        $lang = $brief['language'];
        $first = $brief['first_name'] !== '' ? $brief['first_name'] : $brief['name'];
        $days = $brief['days_since_touch'];
        $last = $brief['notes'][0] ?? null;
        $sources = implode(', ', $brief['sources']);

        $summary = self::t('crm.insight.sim.summary', ['name' => $brief['name'], 'stage' => $brief['stage'] ?? self::t('crm.insight.sim.noStage', [], $lang), 'sources' => $sources, 'count' => count($brief['notes'])], $lang);
        if ($last !== null) {
            $summary .= ' '.self::t('crm.insight.sim.lastNote', ['date' => self::date($last['at'], $lang), 'text' => self::snippet($last['text'])], $lang);
        }

        $overdue = null;
        foreach ($brief['tasks'] as $task) {
            if (! $task['done'] && $task['due'] !== null && $task['due'] <= $brief['today']) {
                $overdue = $task;
                break;
            }
        }

        $channel = $brief['has_phone'] ? 'text' : ($brief['has_email'] ? 'email' : 'note');

        if ($overdue !== null) {
            return [
                'summary' => $summary,
                'next_step' => mb_substr($overdue['title'], 0, 200),
                'why' => self::t('crm.insight.sim.dueTask', ['date' => self::date((string) $overdue['due'], $lang)], $lang),
                'channel' => $brief['has_phone'] ? 'call' : $channel,
                'draft' => null,
            ];
        }

        $why = $days === null ? self::t('crm.insight.sim.neverTouched', [], $lang) : self::t('crm.insight.sim.noTouch', ['days' => $days], $lang);
        if ($last !== null) {
            $why .= '; '.self::t('crm.insight.sim.theySaid', ['text' => self::snippet($last['text'], 60)], $lang);
        }

        return [
            'summary' => $summary,
            'next_step' => self::t('crm.insight.sim.checkIn', ['name' => $first], $lang),
            'why' => mb_substr($why, 0, 300),
            'channel' => $channel,
            'draft' => $channel === 'note' ? null : self::t('crm.insight.sim.draft', ['name' => $first, 'agent' => $brief['agent']], $lang),
        ];
    }

    private static function snippet(string $text, int $length = 120): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1).'…' : $text;
    }

    /** @param  array<string, string|int>  $replace */
    private static function t(string $key, array $replace, string $lang): string
    {
        $text = __($key, $replace, $lang);

        return is_string($text) ? $text : $key;
    }

    private static function date(string $iso, string $lang): string
    {
        $date = CarbonImmutable::parse($iso)->locale($lang);

        return $date instanceof CarbonImmutable ? $date->isoFormat('LL') : $iso;
    }
}
