<?php

declare(strict_types=1);

namespace App\Leads;

use App\Compliance\ComplianceEngine;
use App\Deals\DealAccess;
use App\Models\Contact;
use App\Models\OpenHouse;
use App\Models\OpenHouseVisit;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Tenancy\Tenancy;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Open house sign-in (B14.3a, ADR 0066).
 *
 * The agent starts an open house; visitors sign in by scanning its QR code
 * on their own phone (no app, no account), or on the agent's phone or
 * tablet in kiosk mode, which keeps working offline and sends the
 * sign-ins when it's back online — each once, by the phone's own id.
 *
 * Each sign-in lands in the agent's CRM at once (and their own CRM
 * through write-back), with where they came from, a follow-up to-do and a
 * suggested first message. Visitors say whether they already work with an
 * agent; they then get no sales follow-up.
 *
 * Sign-in stays open from an hour before the start to six hours after.
 *
 * @phpstan-type Visitor array{name: string, email: string|null, phone: string|null, has_agent: bool, pre_approved: bool, timeline: string|null, consent: bool}
 * @phpstan-type KioskVisit array{client_id: string, signed_in_at: string|null, name: string, email: string|null, phone: string|null, has_agent: bool, pre_approved: bool, timeline: string|null, consent: bool}
 * @phpstan-type HouseRow array{id: string, address: string, city: string|null, starts_at: string, ends_at: string, open: bool, url: string, qr_url: string, visits: int}
 * @phpstan-type VisitRow array{id: string, name: string, email: string|null, phone: string|null, has_agent: bool, pre_approved: bool, timeline: string|null, source: string, signed_in_at: string, contact: string|null}
 */
final readonly class OpenHouses
{
    public const TIMELINES = ['now', '3_months', '6_months', 'later', 'just_looking'];

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private DealAccess $access, private ActiveRole $roles, private Leads $leads, private Tenancy $tenancy, private ComplianceEngine $compliance) {}

    /** @return list<HouseRow> */
    public function list(User $agent): array
    {
        $this->access->allow($agent);

        return array_values(array_map(fn (OpenHouse $h): array => $this->row($h), OpenHouse::query()->where('user_id', $agent->id)->withCount('visits')->orderByDesc('starts_at')->limit(30)->get()->all()));
    }

    /** @return HouseRow */
    public function start(User $agent, string $address, ?string $city, CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $this->access->allow($agent);
        $house = OpenHouse::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => $agent->id, 'organisation_id' => $this->roles->for($agent)?->organisation->id,
            'address' => trim($address), 'city' => $city === null || trim($city) === '' ? null : trim($city), 'code' => $this->code(),
            'starts_at' => $startsAt, 'ends_at' => $endsAt->greaterThan($startsAt) ? $endsAt : $startsAt->addHours(2),
        ]);

        return $this->row($house->loadCount('visits'));
    }

    /** @return list<VisitRow> */
    public function visits(User $agent, string $uuid): array
    {
        $house = $this->mine($agent, $uuid);

        return array_values(array_map(fn (OpenHouseVisit $v): array => $this->visitRow($v), $house->visits()->orderByDesc('signed_in_at')->get()->all()));
    }

    /**
     * Sign-ins from the agent's phone or tablet (kiosk mode), possibly made
     * offline: each lands once, by its `client_id`.
     *
     * @param  list<KioskVisit>  $visits
     * @return array{added: int, duplicates: int}
     */
    public function sync(User $agent, string $uuid, array $visits): array
    {
        $house = $this->mine($agent, $uuid);
        $added = 0;

        foreach ($visits as $visit) {
            if (OpenHouseVisit::query()->where('open_house_id', $house->id)->where('client_id', $visit['client_id'])->exists()) {
                continue;
            }

            $at = $visit['signed_in_at'] === null ? CarbonImmutable::now() : CarbonImmutable::parse($visit['signed_in_at']);
            $this->record($house, $visit, 'kiosk', $visit['client_id'], $at->greaterThan(CarbonImmutable::now()) ? CarbonImmutable::now() : $at);
            $added++;
        }

        return ['added' => $added, 'duplicates' => count($visits) - $added];
    }

    /** The open house behind a sign-in link, while sign-in is open. */
    public function byCode(string $code): ?OpenHouse
    {
        $code = strtoupper(trim($code));
        $house = $this->tenancy->asPlatform(static fn (): ?OpenHouse => OpenHouse::query()->where('code', $code)->with('user')->first());

        return $house instanceof OpenHouse && self::isOpen($house) ? $house : null;
    }

    /**
     * A visitor signs in on their own phone.
     *
     * @param  Visitor  $visitor
     */
    public function signIn(OpenHouse $house, array $visitor): void
    {
        $this->tenancy->run($house->organisation_id, fn () => $this->record($house, $visitor, 'qr', null, CarbonImmutable::now()));
    }

    /**
     * What the sign-in page shows: the home and who's hosting.
     *
     * @return array{address: string, city: string|null, agent: string, brokerage: string|null, language: 'en'|'es'}
     */
    public function publicView(OpenHouse $house): array
    {
        return ['address' => $house->address, 'city' => $house->city, 'agent' => (string) $house->user->name, 'brokerage' => $this->compliance->brokerName($house->user), 'language' => $house->user->locale->value === 'es' ? 'es' : 'en'];
    }

    /** The sign-in link as a QR code (SVG), to show on a tablet or print. It only encodes the public link. */
    public function qr(OpenHouse $house): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd)))->writeString(route('open-house.show', $house->code));
    }

    public static function isOpen(OpenHouse $house, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $now->greaterThanOrEqualTo($house->starts_at->subHour()) && $now->lessThanOrEqualTo($house->ends_at->addHours(6));
    }

    /** @param Visitor $visitor */
    private function record(OpenHouse $house, array $visitor, string $source, ?string $clientId, CarbonImmutable $at): OpenHouseVisit
    {
        $agent = $house->user;
        $lang = $agent->locale->value;
        $email = $visitor['email'] === null || trim($visitor['email']) === '' ? null : mb_strtolower(trim($visitor['email']));
        $phone = $visitor['phone'] === null || trim($visitor['phone']) === '' ? null : trim($visitor['phone']);
        $first = (preg_split('/\s+/u', trim($visitor['name'])) ?: [''])[0];
        $timeline = in_array($visitor['timeline'], self::TIMELINES, true) ? $visitor['timeline'] : null;

        $note = self::tr('leads.openHouse.note', [
            'address' => $house->address,
            'date' => self::day($at, $lang),
            'agent' => $visitor['has_agent'] ? self::tr('leads.openHouse.hasAgent', [], $lang) : self::tr('leads.openHouse.noAgent', [], $lang),
            'approved' => $visitor['pre_approved'] ? self::tr('leads.openHouse.approved', [], $lang) : self::tr('leads.openHouse.notApproved', [], $lang),
            'timeline' => $timeline === null ? '—' : self::tr('leads.timelines.'.$timeline, [], $lang),
        ], $lang);
        $draft = self::tr('leads.openHouse.draft', ['name' => $first, 'address' => $house->address, 'agent' => (string) $agent->name], $lang);
        $contact = $this->leads->add($agent, ['name' => trim($visitor['name']), 'email' => $email, 'phone' => $phone], $note, $draft, $visitor['has_agent'], [self::tr('leads.openHouse.tag', [], $lang)], 'open_house');

        return OpenHouseVisit::query()->create([
            'uuid' => (string) Str::uuid7(), 'open_house_id' => $house->id, 'organisation_id' => $house->organisation_id, 'client_id' => $clientId,
            'name' => trim($visitor['name']), 'email' => $email, 'phone' => $phone, 'has_agent' => $visitor['has_agent'], 'pre_approved' => $visitor['pre_approved'],
            'timeline' => $timeline, 'consent' => $visitor['consent'], 'source' => $source, 'contact_id' => $contact->id, 'signed_in_at' => $at,
        ]);
    }

    /** The day in Texas, in the agent's language ("Nov 28, 2026"). */
    private static function day(CarbonImmutable $at, string $lang): string
    {
        $local = $at->setTimezone('America/Chicago')->locale($lang);

        return $local instanceof CarbonImmutable ? $local->isoFormat('ll') : $at->toDateString();
    }

    /** @return HouseRow */
    private function row(OpenHouse $house): array
    {
        return [
            'id' => $house->uuid, 'address' => $house->address, 'city' => $house->city,
            'starts_at' => $house->starts_at->toIso8601String(), 'ends_at' => $house->ends_at->toIso8601String(), 'open' => self::isOpen($house),
            'url' => route('open-house.show', $house->code), 'qr_url' => route('open-house.qr', $house->code),
            'visits' => (int) ($house->visits_count ?? 0),
        ];
    }

    /** @return VisitRow */
    private function visitRow(OpenHouseVisit $v): array
    {
        $contact = $v->contact_id === null ? null : Contact::query()->whereKey($v->contact_id)->value('uuid');

        return [
            'id' => $v->uuid, 'name' => $v->name, 'email' => $v->email, 'phone' => $v->phone, 'has_agent' => $v->has_agent, 'pre_approved' => $v->pre_approved,
            'timeline' => $v->timeline, 'source' => $v->source, 'signed_in_at' => $v->signed_in_at->toIso8601String(), 'contact' => is_string($contact) ? $contact : null,
        ];
    }

    private function mine(User $agent, string $uuid): OpenHouse
    {
        $this->access->allow($agent);

        return OpenHouse::query()->where('user_id', $agent->id)->where('uuid', $uuid)->with('user')->first() ?? throw new NotFoundHttpException;
    }

    private function code(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while ($this->tenancy->asPlatform(static fn (): bool => OpenHouse::query()->where('code', $code)->exists()));

        return $code;
    }

    /** @param array<string, string> $words */
    private static function tr(string $key, array $words, string $lang): string
    {
        $value = __($key, $words, $lang);

        return is_string($value) ? $value : $key;
    }
}
