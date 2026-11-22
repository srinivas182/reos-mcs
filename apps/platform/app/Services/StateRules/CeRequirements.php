<?php

declare(strict_types=1);

namespace App\Services\StateRules;

use App\Models\CeCompletion;
use App\Models\Licence;
use App\Models\StateRule;
use Carbon\CarbonImmutable;

/**
 * "What do I still need?" (A5.2, addition 1): a licence's CE requirement for
 * the current period, read from the state's rules in force today, less what
 * the person has completed.
 *
 * - Every part names the rule (and paragraph) it comes from.
 * - Mandatory courses count once each; Broker Responsibility is added for
 *   sponsoring brokers, designated brokers and delegated supervisors; the
 *   first renewal replaces CE with the qualifying (SAE) hours and Legal
 *   Update I and II.
 * - A course taken twice in the period counts once; commission meeting
 *   hours are capped.
 *
 * @phpstan-type Part array{code: string, title: string, kind: 'mandatory'|'broker_responsibility'|'qualifying'|'elective', hours: float, hours_done: float, done: bool}
 * @phpstan-type Requirement array{state: string, due_on: string|null, period_start: string|null, days_left: int|null, first_renewal: bool, hours_required: float, hours_done: float, hours_left: float, complete: bool, parts: list<Part>, deferral: array{fee_usd: float, days: int}|null, sources: list<array{key: string, citation: string, url: string|null, effective_from: string}>}
 */
final readonly class CeRequirements
{
    public function __construct(private StateRules $rules) {}

    /** @return Requirement|null Null when the state has no CE rules. */
    public function for(Licence $licence, ?CarbonImmutable $today = null): ?array
    {
        $today ??= CarbonImmutable::today();
        $rules = $this->rules->inForce($licence->state_code, $today);
        $hours = self::number($rules['ce.hours'] ?? null);

        if ($hours === null) {
            return null;
        }

        $months = (int) (self::number($rules['ce.period_months'] ?? null) ?? 24);
        $due = $licence->expires_on === null ? null : CarbonImmutable::parse($licence->expires_on)->startOfDay();
        $start = $due?->subMonths($months);

        $completions = CeCompletion::query()
            ->where('user_id', $licence->user_id)
            ->where('state_code', $licence->state_code)
            ->when($start !== null, static fn ($q) => $q->whereDate('completed_on', '>', $start?->toDateString()))
            ->when($start === null, static fn ($q) => $q->whereDate('completed_on', '>', $today->subMonths($months)->toDateString()))
            ->orderBy('completed_on')
            ->get();

        // A course taken twice in the period counts once.
        $taken = [];
        foreach ($completions as $completion) {
            $id = $completion->course_code ?? 'title:'.mb_strtolower(trim($completion->title));
            $taken[$id] ??= $completion;
        }

        $meetingCap = self::number($rules['ce.commission_meeting'] ?? null);
        $meetingHours = 0.0;
        $byCode = [];
        $electiveHours = 0.0;

        foreach ($taken as $completion) {
            $credit = (float) $completion->hours;

            if ($completion->source === 'commission_meeting' && $meetingCap !== null) {
                $credit = max(0.0, min($credit, $meetingCap - $meetingHours));
                $meetingHours += $credit;
            }

            if ($completion->course_code !== null) {
                $byCode[$completion->course_code] = ($byCode[$completion->course_code] ?? 0.0) + $credit;
            } else {
                $electiveHours += $credit;
            }
        }

        $courses = self::courses($rules['ce.mandatory_courses'] ?? null);
        $first = $licence->first_renewal && isset($rules['ce.first_renewal']);
        $parts = [];
        $used = [];

        if ($first) {
            $value = $rules['ce.first_renewal']->value;
            $qualifying = self::float($value['qualifying_hours'] ?? 0);
            $done = min($qualifying, $byCode['sae'] ?? 0.0);
            $used['sae'] = true;
            $parts[] = self::part('sae', __('stateRules.ce.sae'), 'qualifying', $qualifying, $done);

            foreach (is_array($value['courses'] ?? null) ? $value['courses'] : [] as $code) {
                if (! is_string($code)) {
                    continue;
                }

                $course = $courses[$code] ?? null;

                if ($course !== null) {
                    $used[$code] = true;
                    $parts[] = self::part($code, $course['title'], 'mandatory', $course['hours'], min($course['hours'], $byCode[$code] ?? 0.0));
                }
            }
        } else {
            foreach ($courses as $code => $course) {
                $used[$code] = true;
                $parts[] = self::part($code, $course['title'], 'mandatory', $course['hours'], min($course['hours'], $byCode[$code] ?? 0.0));
            }

            $broker = isset($rules['ce.broker_responsibility']) ? $rules['ce.broker_responsibility']->value : [];
            $roles = is_array($broker['roles'] ?? null) ? $broker['roles'] : [];

            if ($broker !== [] && $licence->broker_role !== null && in_array($licence->broker_role->value, $roles, true)) {
                $code = is_string($broker['code'] ?? null) ? $broker['code'] : 'broker_responsibility';
                $brHours = self::float($broker['hours'] ?? 0);
                $used[$code] = true;
                $parts[] = self::part($code, is_string($broker['title'] ?? null) ? $broker['title'] : $code, 'broker_responsibility', $brHours, min($brHours, $byCode[$code] ?? 0.0));
            }

            $mandatoryHours = array_sum(array_column($parts, 'hours'));

            // Extra hours on a mandatory course, and courses that aren't mandatory here, count as electives.
            foreach ($byCode as $code => $credit) {
                $electiveHours += isset($used[$code]) ? max(0.0, $credit - (self::partHours($parts, $code))) : $credit;
            }

            $electives = max(0.0, $hours - $mandatoryHours);

            if ($electives > 0) {
                $parts[] = self::part('electives', __('stateRules.ce.electives'), 'elective', $electives, min($electives, $electiveHours));
            }
        }

        // The rules this requirement was worked out from.
        $applied = ['ce.period_months', 'ce.hours', 'ce.no_repeat', 'ce.deferral', 'ce.mandatory_courses'];

        if (in_array('broker_responsibility', array_column($parts, 'kind'), true)) {
            $applied[] = 'ce.broker_responsibility';
        }

        if ($meetingHours > 0) {
            $applied[] = 'ce.commission_meeting';
        }

        if ($first) {
            $applied[] = 'ce.first_renewal';
        }

        $required = $first ? array_sum(array_column($parts, 'hours')) : max($hours, array_sum(array_column($parts, 'hours')));
        $done = array_sum(array_column($parts, 'hours_done'));
        $deferral = $rules['ce.deferral'] ?? null;

        return [
            'state' => $licence->state_code,
            'due_on' => $due?->toDateString(),
            'period_start' => $start?->toDateString(),
            'days_left' => $due === null ? null : (int) $today->diffInDays($due, false),
            'first_renewal' => $first,
            'hours_required' => round($required, 1),
            'hours_done' => round($done, 1),
            'hours_left' => round(max(0.0, $required - $done), 1),
            'complete' => $done >= $required && array_filter($parts, static fn (array $p): bool => ! $p['done']) === [],
            'parts' => $parts,
            'deferral' => $deferral === null ? null : ['fee_usd' => self::float($deferral->value['fee_usd'] ?? 0), 'days' => (int) self::float($deferral->value['days'] ?? 0)],
            'sources' => array_values(array_map(static fn (StateRule $rule): array => [
                'key' => $rule->key,
                'citation' => $rule->citation,
                'url' => $rule->source_url,
                'effective_from' => $rule->effective_from->toDateString(),
            ], array_filter($rules, static fn (StateRule $rule): bool => in_array($rule->key, $applied, true)))),
        ];
    }

    /**
     * @param  'mandatory'|'broker_responsibility'|'qualifying'|'elective'  $kind
     * @return Part
     */
    private static function part(string $code, string $title, string $kind, float $hours, float $done): array
    {
        return ['code' => $code, 'title' => $title, 'kind' => $kind, 'hours' => round($hours, 1), 'hours_done' => round($done, 1), 'done' => $done >= $hours];
    }

    /** @param list<Part> $parts */
    private static function partHours(array $parts, string $code): float
    {
        foreach ($parts as $part) {
            if ($part['code'] === $code) {
                return $part['hours'];
            }
        }

        return 0.0;
    }

    /** @return array<string, array{title: string, hours: float}> */
    private static function courses(?StateRule $rule): array
    {
        $courses = [];

        $value = $rule === null ? [] : $rule->value;
        $list = $value['courses'] ?? null;

        foreach (is_array($list) ? $list : [] as $course) {
            if (is_array($course) && is_string($course['code'] ?? null) && is_string($course['title'] ?? null)) {
                $courses[$course['code']] = ['title' => $course['title'], 'hours' => self::float($course['hours'] ?? 0)];
            }
        }

        return $courses;
    }

    private static function number(?StateRule $rule): ?float
    {
        $value = $rule?->value['number'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    private static function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
