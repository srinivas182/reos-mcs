<?php

declare(strict_types=1);

namespace App\ContractReview;

use App\Models\StateRule;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * REOS's own checks on what a review read (A12.2, ADR 0048; screen 65):
 * the deadlines worked out from the Effective Date, and findings ranked
 * High (likely to cost the deal or break a rule) and Check (worth a second
 * look), each with the page it's on and, when there is one, its date and
 * rule. Only facts from the document are used; nothing is legal advice.
 *
 * @phpstan-type Finding array{code: string, severity: 'high'|'check', title: string, detail: string, page: int|null, date: string|null, citation: string|null, url: string|null}
 * @phpstan-type Deadline array{code: string, kind: string, label: string, due_on: string, page: int|null, basis: string}
 */
final class ContractChecks
{
    /** Deadlines counted in days after the Effective Date: term code => [deadline code, deal date kind]. */
    private const AFTER_EFFECTIVE = [
        'earnest_days' => ['earnest_money', 'other'],
        'option_days' => ['option_period', 'option_period'],
        'survey_days' => ['survey', 'survey'],
        'financing_days' => ['financing', 'financing'],
    ];

    /** Special provisions that read as legal drafting rather than business details (22 TAC §537.11). */
    private const LEGAL_WORDING = '/\b(indemnif\w*|hold harmless|notwithstanding|liquidated damages|waives?\b[^.]{0,40}\brights?|in no event|attorney[\'’]?s? fees)\b/iu';

    /** Terms whose change since the earlier version is worth a finding. */
    public const WATCHED = ['sales_price', 'cash_portion', 'financing', 'earnest_money', 'option_fee', 'option_days', 'financing_days', 'closing_date', 'special_provisions', 'possession'];

    /**
     * @param  array<string, StateRule>  $rules  the state's rules in force, by key
     * @param  array<string, string>|null  $earlier  the earlier version's terms, by code
     * @return array{0: list<Finding>, 1: list<Deadline>, 2: CarbonImmutable|null}
     */
    public static function run(ContractReading $reading, array $rules, ?array $earlier, string $language): array
    {
        $findings = [];
        $deadlines = [];
        $line = static fn (string $key, array $replace = []): string => self::line($key, $replace, $language);

        if ($reading->form === null) {
            $findings[] = self::finding($language, 'general', 'check', null);
        }

        $effective = self::date($reading->value('effective_date'));

        if ($effective === null) {
            $findings[] = self::finding($language, 'no_effective_date', 'high', $reading->page('effective_date'));
        }

        // Deadlines from the Effective Date, then the closing date.
        foreach (self::AFTER_EFFECTIVE as $term => [$code, $kind]) {
            $days = $reading->value($term);

            if ($effective !== null && $days !== null && ctype_digit($days)) {
                $deadlines[] = [
                    'code' => $code, 'kind' => $kind, 'label' => $line("contracts.deadlines.{$code}"), 'due_on' => $effective->addDays((int) $days)->toDateString(),
                    'page' => $reading->page($term), 'basis' => $line('contracts.deadlines.after', ['days' => $days]),
                ];
            }
        }

        $closing = self::date($reading->value('closing_date'));

        if ($closing !== null) {
            $deadlines[] = ['code' => 'closing', 'kind' => 'closing', 'label' => $line('contracts.deadlines.closing'), 'due_on' => $closing->toDateString(), 'page' => $reading->page('closing_date'), 'basis' => $line('contracts.deadlines.onOrBefore')];
        }

        foreach ($deadlines as $d) {
            $day = CarbonImmutable::parse($d['due_on']);

            if ($day->isWeekend()) {
                $findings[] = self::finding($language, 'weekend', 'check', $d['page'], $d['due_on'], ['deadline' => $d['label'], 'day' => self::dayName($day, $language)]);
            }
        }

        // Money.
        $earnest = TrecForms::amount($reading->value('earnest_money'));

        if ($reading->form !== null && ($earnest === null || $earnest <= 0.0)) {
            $findings[] = self::finding($language, 'no_earnest_money', 'high', $reading->page('earnest_money'));
        }

        if ($reading->form !== null && $reading->value('escrow_agent') === null) {
            $findings[] = self::finding($language, 'no_escrow_agent', 'check', $reading->page('earnest_money'));
        }

        $price = TrecForms::amount($reading->value('sales_price'));
        $cash = TrecForms::amount($reading->value('cash_portion')) ?? 0.0;
        $financing = TrecForms::amount($reading->value('financing')) ?? 0.0;

        if ($price !== null && abs($cash + $financing - $price) > 0.5) {
            $findings[] = self::finding($language, 'price_math', 'high', $reading->page('sales_price'), null, ['cash' => self::money($cash), 'financing' => self::money($financing), 'price' => self::money($price)]);
        }

        if ($financing > 0.0 && ! in_array('third_party_financing', $reading->addenda, true) && ! in_array('seller_financing', $reading->addenda, true)) {
            $findings[] = self::finding($language, 'no_financing_addendum', 'high', $reading->page('financing'));
        }

        // The option period.
        if ($reading->form !== null && $reading->value('option_days') === null) {
            $findings[] = self::finding($language, 'no_option', 'check', $reading->page('option_fee'));
        } elseif ($reading->value('option_days') !== null && TrecForms::amount($reading->value('option_fee')) === null) {
            $findings[] = self::finding($language, 'no_option_fee', 'check', $reading->page('option_days'));
        }

        // Closing before the buyer's own deadlines.
        foreach ($deadlines as $d) {
            if ($closing !== null && in_array($d['code'], ['option_period', 'financing'], true) && CarbonImmutable::parse($d['due_on'])->gte($closing)) {
                $findings[] = self::finding($language, 'closing_early', 'high', $reading->page('closing_date'), $closing->toDateString(), ['deadline' => $d['label']]);
            }
        }

        if ($closing !== null && $effective !== null && $closing->lt($effective)) {
            $findings[] = self::finding($language, 'closing_before_effective', 'high', $reading->page('closing_date'), $closing->toDateString());
        }

        // Special provisions: factual statements and business details only.
        $special = $reading->value('special_provisions');

        if ($special !== null && preg_match(self::LEGAL_WORDING, $special) === 1) {
            $findings[] = self::finding($language, 'special_provisions', 'check', $reading->page('special_provisions'), null, [], $rules['forms.promulgated'] ?? null);
        }

        // What changed since the earlier version.
        foreach ($earlier === null ? [] : self::WATCHED as $code) {
            $now = $reading->value($code);
            $before = $earlier[$code] ?? null;

            if ($now !== $before && ($now !== null || $before !== null)) {
                $findings[] = self::finding($language, 'changed', 'check', $reading->page($code), null, ['term' => $line("contracts.terms.{$code}"), 'from' => $before ?? '—', 'to' => $now ?? '—']);
            }
        }

        // The reader's own findings (Claude), after REOS's.
        foreach ($reading->findings as $f) {
            $findings[] = ['code' => 'reader', 'severity' => $f['severity'], 'title' => $f['title'], 'detail' => $f['detail'], 'page' => $f['page'], 'date' => null, 'citation' => null, 'url' => null];
        }

        usort($findings, static fn (array $a, array $b): int => [$a['severity'] === 'high' ? 0 : 1, $a['page'] ?? 99] <=> [$b['severity'] === 'high' ? 0 : 1, $b['page'] ?? 99]);
        usort($deadlines, static fn (array $a, array $b): int => $a['due_on'] <=> $b['due_on']);

        return [$findings, $deadlines, $effective];
    }

    /**
     * @param  'high'|'check'  $severity
     * @param  array<string, string>  $replace
     * @return Finding
     */
    private static function finding(string $language, string $code, string $severity, ?int $page, ?string $date = null, array $replace = [], ?StateRule $rule = null): array
    {
        return [
            'code' => $code, 'severity' => $severity, 'title' => self::line("contracts.findings.{$code}.title", $replace, $language), 'detail' => self::line("contracts.findings.{$code}.detail", $replace, $language),
            'page' => $page, 'date' => $date, 'citation' => $rule?->citation, 'url' => $rule?->source_url,
        ];
    }

    /** "24th day of November, 2026" or "January 9, 2027" → a date. */
    public static function date(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        $text = (string) preg_replace('/^(\d{1,2})(?:st|nd|rd|th)?\s+day of\s+([A-Za-z]+),?\s*(\d{4})$/i', '$2 $1, $3', trim($value));

        try {
            return CarbonImmutable::createFromFormat('!F j, Y', str_replace('.', '', $text)) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function dayName(CarbonImmutable $day, string $language): string
    {
        $local = $day->locale($language);

        return ($local instanceof CarbonImmutable ? $local : $day)->isoFormat('dddd');
    }

    private static function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    /** @param  array<string, string>  $replace */
    private static function line(string $key, array $replace, string $language): string
    {
        $line = __($key, $replace, $language);

        return is_string($line) ? $line : $key;
    }
}
