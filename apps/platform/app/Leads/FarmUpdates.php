<?php

declare(strict_types=1);

namespace App\Leads;

use App\Compliance\ComplianceEngine;
use App\Compliance\ComplianceLog;
use App\Deals\DealAccess;
use App\Mls\ListingPurpose;
use App\Mls\ListingService;
use App\Mls\MlsFeeds;
use App\Models\Contact;
use App\Models\FarmArea;
use App\Models\FarmSend;
use App\Models\MlsListing;
use App\Models\MlsMemberLink;
use App\Models\User;
use App\Notifications\FarmUpdateMail;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Farming market updates (B14.3c, ADR 0068): for each area an agent farms,
 * a monthly update from the agent's MLS, emailed to the contacts tagged
 * for the area.
 *
 * - **Figures** are counts and medians over the last three months, from
 *   listings the MLS lets the public see. Sale prices appear only where
 *   every MLS involved allows them on public material (Texas is a
 *   non-disclosure state, so not by default); counts of sales always may.
 * - **Every update is checked** by the compliance engine as an
 *   advertisement (the `newsletter` channel): the broker's name, no
 *   misleading claims, fair housing wording. A blocked update can't be
 *   sent, and the check is in the broker's compliance log.
 * - **Every email** names the agent and broker, carries the sender's postal
 *   address and a one-click unsubscribe (CAN-SPAM, RFC 8058). Someone who
 *   unsubscribes never gets another update from that agent. At most one
 *   update per area a week.
 *
 * @phpstan-type Stats array{active: int, pending: int, new_listings: int, sold: int, median_price: int|null, median_days: int|null, median_sold_price: int|null, months_supply: float|null, from: string, to: string}
 * @phpstan-type AreaRow array{id: string, name: string, city: string|null, postal_code: string|null, tag: string, mailing_address: string, recipients: int, last_sent_at: string|null}
 * @phpstan-type Draft array{area: AreaRow, stats: Stats, subject: string, body: string, attribution: string, check: array{ok: bool, issues: list<array{code: string, severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>}, can_send: bool, next_send_at: string|null}
 */
final readonly class FarmUpdates
{
    public const MONTHS = 3;

    public const DAYS_BETWEEN = 7;

    public function __construct(
        private DealAccess $access,
        private ActiveRole $roles,
        private ListingService $listings,
        private MlsFeeds $feeds,
        private ComplianceEngine $compliance,
        private ComplianceLog $log,
        private AuditLogger $audit,
        private Tenancy $tenancy,
    ) {}

    /** @return list<AreaRow> */
    public function areas(User $agent): array
    {
        $this->access->allow($agent);

        return array_values(FarmArea::query()->where('user_id', $agent->id)->orderBy('name')->get()->map(fn (FarmArea $a): array => $this->row($a))->all());
    }

    /**
     * @param  array{name: string, city: string|null, postal_code: string|null, tag: string, mailing_address: string}  $input
     * @return AreaRow
     */
    public function add(User $agent, array $input): array
    {
        $this->access->allow($agent);
        $area = FarmArea::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => $agent->id, 'organisation_id' => $this->roles->for($agent)?->organisation->id,
            'name' => trim($input['name']), 'city' => self::clean($input['city']), 'postal_code' => self::clean($input['postal_code']),
            'tag' => trim($input['tag']), 'mailing_address' => trim($input['mailing_address']),
        ]);

        return $this->row($area);
    }

    public function remove(User $agent, string $uuid): void
    {
        $this->mine($agent, $uuid)->delete();
    }

    /**
     * This month's update for an area: the figures, a draft in the agent's
     * language, and the compliance check.
     *
     * @return Draft
     */
    public function draft(User $agent, string $uuid): array
    {
        $area = $this->mine($agent, $uuid);
        $stats = $this->stats($agent, $area);
        [$subject, $body] = $this->words($agent, $area, $stats);

        return $this->drafted($agent, $area, $stats, $subject, $body);
    }

    /**
     * Sends the update (edited or not) to the area's contacts, once the
     * compliance check passes.
     *
     * @return Draft
     */
    public function send(User $agent, string $uuid, string $subject, string $body): array
    {
        $area = $this->mine($agent, $uuid);
        $stats = $this->stats($agent, $area);
        $draft = $this->drafted($agent, $area, $stats, trim($subject), trim($body));

        if (! $draft['check']['ok']) {
            $this->log->record($agent, 'newsletter', 'blocked', $draft['check']['issues'], subjectType: 'farm_area', subjectId: $area->uuid);

            throw ValidationException::withMessages(['body' => __('leads.farm.blocked')]);
        }

        if (! $draft['can_send']) {
            throw ValidationException::withMessages(['area' => __('leads.farm.tooSoon', ['date' => (string) $draft['next_send_at']])]);
        }

        $recipients = $this->recipients($area);

        if ($recipients === []) {
            throw ValidationException::withMessages(['area' => __('leads.farm.nobody', ['tag' => $area->tag])]);
        }

        $send = FarmSend::query()->create([
            'uuid' => (string) Str::uuid7(), 'farm_area_id' => $area->id, 'organisation_id' => $area->organisation_id,
            'subject' => $draft['subject'], 'body' => $draft['body'], 'stats' => $stats, 'recipients' => count($recipients), 'sent_at' => CarbonImmutable::now(),
        ]);

        foreach ($recipients as $email) {
            Notification::route('mail', $email)->notify(new FarmUpdateMail($agent, $area, $draft['subject'], $draft['body'], $draft['attribution'], $this->compliance->brokerName($agent), $email));
        }

        $this->log->record($agent, 'newsletter', 'checked', $draft['check']['issues'], subjectType: 'farm_send', subjectId: $send->uuid);
        $this->audit->record('farm.sent', 'farm_area', $area->uuid, ['recipients' => count($recipients)], $agent);

        return $this->drafted($agent, $area->refresh(), $stats, $draft['subject'], $draft['body']);
    }

    /** One-click unsubscribe from the link in the email (signed; it carries the address's hash). */
    public function unsubscribe(int $agentId, string $hash): void
    {
        $agent = User::query()->find($agentId);

        if ($agent === null || preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
            throw new NotFoundHttpException;
        }
        $organisation = $this->roles->for($agent)?->organisation->id;

        $this->tenancy->run($organisation, static fn () => DB::table('farm_opt_outs')->insertOrIgnore([
            'user_id' => $agent->id, 'organisation_id' => $organisation, 'email_hash' => $hash, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public static function hash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /** @return Stats */
    private function stats(User $agent, FarmArea $area): array
    {
        $integrations = MlsMemberLink::query()->where('user_id', $agent->id)->where('status', MlsMemberLink::CONFIRMED)->distinct()->pluck('integration')->filter(static fn (mixed $v): bool => is_string($v))->values()->all();

        if ($integrations === []) {
            throw ValidationException::withMessages(['area' => __('leads.farm.noMls')]);
        }

        $today = CarbonImmutable::now('America/Chicago')->startOfDay();
        $since = $today->subMonths(self::MONTHS);
        $listings = MlsListing::query()->whereIn('integration', $integrations)->where('idx', true)
            ->when($area->city !== null, static fn ($q) => $q->whereRaw('lower(city) = ?', [mb_strtolower((string) $area->city)]))
            ->when($area->postal_code !== null, static fn ($q) => $q->where('postal_code', $area->postal_code))
            ->where(static fn ($q) => $q->whereIn('status', ['active', 'under_contract', 'pending'])->orWhere('listed_on', '>=', $since->toDateString())->orWhere(static fn ($c) => $c->where('status', 'closed')->where('close_date', '>=', $since->toDateString())))
            ->limit(5000)->get();

        $active = $prices = $days = $sold = [];
        $pending = $new = 0;
        $soldPublic = true;

        foreach ($listings as $listing) {
            $rules = $this->listings->rules($listing->integration);

            if (! $this->listings->isFresh($listing->integration)) {
                continue;
            }

            if ($listing->listed_on !== null && $listing->listed_on->greaterThanOrEqualTo($since)) {
                $new++;
            }

            match ($listing->status) {
                'active' => $active[] = $listing,
                'under_contract', 'pending' => $pending++,
                default => null,
            };

            if ($listing->status === 'closed' && $listing->close_date !== null && $listing->close_date->greaterThanOrEqualTo($since)) {
                $sold[] = $listing->close_price;
                $soldPublic = $soldPublic && $rules->soldPublic;
            }
        }

        foreach ($active as $listing) {
            $view = $this->listings->view($listing, ListingPurpose::Public);

            if ($view === null) {
                continue;
            }

            if ($view['price'] !== null) {
                $prices[] = $view['price'];
            }

            if ($view['days_on_market'] !== null) {
                $days[] = $view['days_on_market'];
            }
        }

        $soldPrices = array_values(array_filter($sold, is_int(...)));

        return [
            'active' => count($active), 'pending' => $pending, 'new_listings' => $new, 'sold' => count($sold),
            'median_price' => self::median($prices), 'median_days' => self::median($days),
            'median_sold_price' => $soldPublic && $soldPrices !== [] ? self::median($soldPrices) : null,
            'months_supply' => $sold === [] ? null : round(count($active) / (count($sold) / self::MONTHS), 1),
            'from' => $since->toDateString(), 'to' => $today->toDateString(),
        ];
    }

    /**
     * @param  Stats  $stats
     * @return array{0: string, 1: string}
     */
    private function words(User $agent, FarmArea $area, array $stats): array
    {
        $lang = $agent->locale->value;
        $money = static fn (?int $n): string => $n === null ? '—' : '$'.number_format($n);
        $month = CarbonImmutable::now('America/Chicago')->locale($lang);
        $words = ['area' => $area->name, 'month' => $month instanceof CarbonImmutable ? $month->isoFormat('MMMM YYYY') : ''];
        $lines = [
            self::tr('leads.farm.draft.intro', $words, $lang),
            self::tr('leads.farm.draft.active', ['count' => (string) $stats['active'], 'price' => $money($stats['median_price'])], $lang),
            self::tr('leads.farm.draft.new', ['count' => (string) $stats['new_listings']], $lang),
            $stats['median_sold_price'] === null
                ? self::tr('leads.farm.draft.sold', ['count' => (string) $stats['sold']], $lang)
                : self::tr('leads.farm.draft.soldPrice', ['count' => (string) $stats['sold'], 'price' => $money($stats['median_sold_price'])], $lang),
        ];

        if ($stats['median_days'] !== null) {
            $lines[] = self::tr('leads.farm.draft.days', ['days' => (string) $stats['median_days']], $lang);
        }

        if ($stats['months_supply'] !== null) {
            $lines[] = self::tr('leads.farm.draft.supply', ['months' => number_format($stats['months_supply'], 1)], $lang);
        }

        $lines[] = self::tr('leads.farm.draft.close', ['area' => $area->name], $lang);
        $lines[] = trim((string) $agent->name.', '.($this->compliance->brokerName($agent) ?? ''), ', ');

        return [self::tr('leads.farm.draft.subject', $words, $lang), implode("\n\n", $lines)];
    }

    /**
     * @param  Stats  $stats
     * @return Draft
     */
    private function drafted(User $agent, FarmArea $area, array $stats, string $subject, string $body): array
    {
        $names = MlsMemberLink::query()->where('user_id', $agent->id)->where('status', MlsMemberLink::CONFIRMED)->distinct()->pluck('integration')
            ->map(fn (mixed $i): string => is_string($i) ? ($this->feeds->find($i)->mls->shortName ?? $i) : '')->filter()->unique()->implode(', ');
        $attribution = self::tr('leads.farm.attribution', ['mls' => $names, 'area' => $area->name, 'from' => $stats['from'], 'to' => $stats['to']], $agent->locale->value);
        $check = $this->compliance->check($agent, ['channel' => 'newsletter', 'text' => $subject."\n\n".$body, 'attribution' => null]);
        $last = $area->sends()->latest('sent_at')->first()?->sent_at;
        $next = $last?->addDays(self::DAYS_BETWEEN);

        return [
            'area' => $this->row($area), 'stats' => $stats, 'subject' => $subject, 'body' => $body, 'attribution' => $attribution,
            'check' => $check, 'can_send' => $next === null || $next->lessThanOrEqualTo(CarbonImmutable::now()), 'next_send_at' => $next?->toIso8601String(),
        ];
    }

    /** @return list<string> the emails of the agent's contacts with the area's tag, less anyone who unsubscribed */
    private function recipients(FarmArea $area): array
    {
        $out = DB::table('farm_opt_outs')->where('user_id', $area->user_id)->pluck('email_hash')->all();
        $emails = [];

        $contacts = Contact::query()->where('owner_type', 'user')->where('owner_id', $area->user_id)->whereNull('merged_into_id')
            ->whereHas('tags', static fn ($q) => $q->whereRaw('lower(name) = ?', [mb_strtolower($area->tag)]))->limit(2000)->get();

        foreach ($contacts as $contact) {
            $email = $contact->emails[0]['value'] ?? null;

            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && ! in_array(self::hash($email), $out, true)) {
                $emails[mb_strtolower($email)] = true;
            }
        }

        return array_keys($emails);
    }

    private function mine(User $agent, string $uuid): FarmArea
    {
        $this->access->allow($agent);

        return FarmArea::query()->where('user_id', $agent->id)->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;
    }

    /** @return AreaRow */
    private function row(FarmArea $area): array
    {
        return [
            'id' => $area->uuid, 'name' => $area->name, 'city' => $area->city, 'postal_code' => $area->postal_code, 'tag' => $area->tag,
            'mailing_address' => $area->mailing_address, 'recipients' => count($this->recipients($area)),
            'last_sent_at' => $area->sends()->latest('sent_at')->first()?->sent_at->toIso8601String(),
        ];
    }

    /** @param list<int> $values */
    private static function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $mid = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$mid] : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    private static function clean(?string $s): ?string
    {
        return $s === null || trim($s) === '' ? null : trim($s);
    }

    /** @param array<string, string> $words */
    private static function tr(string $key, array $words, string $lang): string
    {
        $text = __($key, $words, $lang);

        return is_string($text) ? $text : $key;
    }
}
