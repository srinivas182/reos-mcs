<?php

declare(strict_types=1);

namespace App\Mls;

use App\Deals\DealAccess;
use App\Mls\Reso\ResoRecords;
use App\Models\Licence;
use App\Models\MlsFeed;
use App\Models\MlsListing;
use App\Models\MlsMember;
use App\Models\MlsMemberLink;
use App\Models\MlsOffice;
use App\Models\MlsOpenHouse;
use App\Models\User;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * My listings (B10.1; agent web and phone, beside Deals). An agent's MLS
 * member records are found by their TREC licence (`MemberStateLicense`) and
 * confirmed by them; listings where they are the list or co-list agent are
 * theirs. A member record links to one REOS account.
 *
 * @phpstan-type MemberRow array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}
 * @phpstan-type ListingRow array{uuid: string, mls: string, short_name: string, listing_id: string|null, status: string, role: 'list'|'co_list', address: string, city: string|null, price: int|null, close_price: int|null, beds: int|null, baths: float|null, area: int|null, photo: array{url: string, caption: string|null}|null, photos: int, listed_on: string|null, days_on_market: int|null, office: string|null, open_house: array{starts_at: string, ends_at: string, kind: string}|null}
 * @phpstan-type Overview array{licensed: bool, linked: list<MemberRow>, candidates: list<MemberRow>, listings: list<ListingRow>, sample: bool, synced_at: string|null}
 */
final readonly class MyListings
{
    public function __construct(private MlsFeeds $feeds, private DealAccess $access) {}

    /** @return Overview */
    public function overview(User $user): array
    {
        $this->access->allow($user);
        $linked = $this->linkedMembers($user);
        $mlses = array_values(array_unique(array_map(static fn (MlsMember $m): string => $m->integration, $linked)));
        $synced = MlsFeed::query()->whereIn('integration', $mlses)->whereNotNull('synced_at')->min('synced_at');

        return [
            'licensed' => $this->licenceDigits($user) !== [],
            'linked' => array_map($this->memberRow(...), $linked),
            'candidates' => array_map($this->memberRow(...), $this->candidates($user)),
            'listings' => $this->listings($user, $linked),
            'sample' => array_any($mlses, $this->feeds->isSample(...)),
            'synced_at' => is_string($synced) ? CarbonImmutable::parse($synced)->toIso8601String() : null,
        ];
    }

    /** "This is me": link a member record found by my licence. */
    public function confirm(User $user, string $integration, string $memberKey): void
    {
        $this->access->allow($user);
        $member = $this->candidate($user, $integration, $memberKey);
        MlsMemberLink::query()->updateOrCreate(
            ['user_id' => $user->id, 'integration' => $member->integration, 'member_key' => $member->member_key],
            ['status' => MlsMemberLink::CONFIRMED],
        );
    }

    /** "Not me": stop suggesting this member record. */
    public function decline(User $user, string $integration, string $memberKey): void
    {
        $this->access->allow($user);
        $member = $this->candidate($user, $integration, $memberKey);
        MlsMemberLink::query()->updateOrCreate(
            ['user_id' => $user->id, 'integration' => $member->integration, 'member_key' => $member->member_key],
            ['status' => MlsMemberLink::DECLINED],
        );
    }

    /** Unlink my member record at an MLS (undo links it again). */
    public function unlink(User $user, string $integration, string $memberKey): void
    {
        $this->access->allow($user);
        MlsMemberLink::query()->where('user_id', $user->id)->where('integration', $integration)->where('member_key', $memberKey)
            ->where('status', MlsMemberLink::CONFIRMED)->delete();
    }

    /** @return list<MlsMember> */
    private function linkedMembers(User $user): array
    {
        $links = MlsMemberLink::query()->where('user_id', $user->id)->where('status', MlsMemberLink::CONFIRMED)->get();

        if ($links->isEmpty()) {
            return [];
        }

        return array_values(MlsMember::query()
            ->where(static function (Builder $query) use ($links): void {
                foreach ($links as $link) {
                    $query->orWhere(static fn (Builder $q) => $q->where('integration', $link->integration)->where('member_key', $link->member_key));
                }
            })
            ->orderBy('integration')->get()->all());
    }

    /**
     * Active member records under my licence numbers that I haven't answered
     * and no one else has linked.
     *
     * @return list<MlsMember>
     */
    private function candidates(User $user): array
    {
        $digits = $this->licenceDigits($user);

        if ($digits === []) {
            return [];
        }

        $answered = MlsMemberLink::query()->where('user_id', $user->id)->get()
            ->map(static fn (MlsMemberLink $l): string => $l->integration.'|'.$l->member_key)->all();
        $taken = MlsMemberLink::query()->where('user_id', '!=', $user->id)->where('status', MlsMemberLink::CONFIRMED)->get()
            ->map(static fn (MlsMemberLink $l): string => $l->integration.'|'.$l->member_key)->all();
        $live = array_map(static fn ($d): string => $d->id, $this->feeds->all());

        return array_values(MlsMember::query()->whereIn('licence_digits', $digits)->whereIn('integration', $live)->where('active', true)
            ->orderBy('integration')->get()
            ->reject(static fn (MlsMember $m): bool => in_array($m->integration.'|'.$m->member_key, [...$answered, ...$taken], true))
            ->all());
    }

    private function candidate(User $user, string $integration, string $memberKey): MlsMember
    {
        foreach ($this->candidates($user) as $member) {
            if ($member->integration === $integration && $member->member_key === $memberKey) {
                return $member;
            }
        }

        throw ValidationException::withMessages(['member_key' => __('listings.errors.notYours')]);
    }

    /** @return list<string> */
    private function licenceDigits(User $user): array
    {
        return array_values(array_filter(
            Licence::query()->where('user_id', $user->id)->pluck('number')->map(static fn (mixed $n): ?string => ResoRecords::licenceDigits(is_string($n) ? $n : null))->all(),
            static fn (?string $d): bool => $d !== null,
        ));
    }

    /** @return MemberRow */
    private function memberRow(MlsMember $member): array
    {
        $office = $member->office_key === null ? null : MlsOffice::query()->where('integration', $member->integration)->where('office_key', $member->office_key)->first();

        return [
            'mls' => $member->integration,
            'short_name' => $this->feeds->find($member->integration)?->mls->shortName ?? $member->integration,
            'member_key' => $member->member_key,
            'name' => $member->name,
            'office' => $office?->name,
            'office_city' => $office?->city,
        ];
    }

    /**
     * @param  list<MlsMember>  $members
     * @return list<ListingRow>
     */
    private function listings(User $user, array $members): array
    {
        if ($members === []) {
            return [];
        }

        $keys = [];

        foreach ($members as $member) {
            $keys[$member->integration][] = $member->member_key;
        }

        $listings = MlsListing::query()
            ->where(static function (Builder $query) use ($keys): void {
                foreach ($keys as $integration => $memberKeys) {
                    $query->orWhere(static fn (Builder $q) => $q->where('integration', $integration)
                        ->where(static fn (Builder $w) => $w->whereIn('list_agent_key', $memberKeys)->orWhereIn('co_list_agent_key', $memberKeys)));
                }
            })
            ->orderByDesc('modified_at')->limit(200)->get();

        $openHouses = $this->nextOpenHouses($listings);
        $today = CarbonImmutable::now(StudyPlans::TIMEZONE)->startOfDay();
        $rows = [];

        foreach ($listings->sortBy(static fn (MlsListing $l): int => (int) array_search($l->status, MlsListing::ORDER, true)) as $l) {
            $listed = $l->listed_on;
            $end = $l->status === 'closed' && $l->close_date !== null ? $l->close_date : $today;
            $house = $openHouses[$l->integration.'|'.$l->listing_key] ?? null;
            $unit = $l->unit !== null && ! str_contains((string) $l->street, $l->unit) ? ' #'.$l->unit : '';

            $rows[] = [
                'uuid' => $l->uuid,
                'mls' => $l->integration,
                'short_name' => $this->feeds->find($l->integration)?->mls->shortName ?? $l->integration,
                'listing_id' => $l->listing_id,
                'status' => $l->status,
                'role' => in_array($l->list_agent_key, $keys[$l->integration] ?? [], true) ? 'list' : 'co_list',
                'address' => $l->street !== null ? $l->street.$unit : ($l->city ?? ''),
                'city' => $l->city,
                'price' => $l->list_price,
                'close_price' => $l->close_price,
                'beds' => $l->bedrooms,
                'baths' => $l->bathrooms === null ? null : (float) $l->bathrooms,
                'area' => $l->living_area,
                'photo' => $l->photos[0] ?? null,
                'photos' => count($l->photos),
                'listed_on' => $listed?->toDateString(),
                'days_on_market' => $listed === null ? null : max(0, (int) $listed->diffInDays($end)),
                'office' => $l->list_office_name,
                'open_house' => $house === null ? null : ['starts_at' => $house->starts_at->toIso8601String(), 'ends_at' => $house->ends_at->toIso8601String(), 'kind' => $house->kind],
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, MlsListing>  $listings
     * @return array<string, MlsOpenHouse> "integration|listing_key" => the next open house
     */
    private function nextOpenHouses(Collection $listings): array
    {
        if ($listings->isEmpty()) {
            return [];
        }

        $next = [];
        $houses = MlsOpenHouse::query()->whereIn('listing_key', $listings->pluck('listing_key')->all())->where('ends_at', '>', now())->orderBy('starts_at')->get();

        foreach ($houses as $house) {
            $next[$house->integration.'|'.$house->listing_key] ??= $house;
        }

        return $next;
    }
}
