<?php

declare(strict_types=1);

namespace App\Mls;

use App\Models\CalendarEvent;
use App\Models\MlsListing;
use App\Models\MlsMemberLink;
use App\Models\MlsOpenHouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Open houses from the MLS on the agent's REOS calendar (B10.2): one event
 * per upcoming open house of a listing where they're the list or co-list
 * agent. The MLS is the source: the events are read-only in REOS, follow
 * the MLS's changes, and disappear when the MLS cancels them.
 */
final readonly class OpenHouseCalendar
{
    public function __construct(private ListingService $listings) {}

    /** After an MLS sync: every agent linked at that MLS. */
    public function syncMls(string $integration): void
    {
        $users = MlsMemberLink::query()->where('integration', $integration)->where('status', MlsMemberLink::CONFIRMED)->distinct()->pluck('user_id');

        foreach (User::query()->whereIn('id', $users)->get() as $user) {
            $this->sync($user, $integration);
        }
    }

    /** One agent's open houses at one MLS. */
    public function sync(User $user, string $integration): void
    {
        $keys = MlsMemberLink::query()->where('user_id', $user->id)->where('integration', $integration)->where('status', MlsMemberLink::CONFIRMED)->pluck('member_key')->all();
        $listings = $keys === [] ? collect() : MlsListing::query()->where('integration', $integration)
            ->where(static fn ($q) => $q->whereIn('list_agent_key', $keys)->orWhereIn('co_list_agent_key', $keys))
            ->whereIn('status', ['coming_soon', 'active', 'under_contract', 'pending'])->get()->keyBy('listing_key');
        $houses = $listings->isEmpty() ? collect() : MlsOpenHouse::query()->where('integration', $integration)
            ->whereIn('listing_key', $listings->keys()->all())->where('ends_at', '>', now())->get();
        $locale = $user->preferredLocale();

        DB::transaction(function () use ($user, $integration, $listings, $houses, $locale): void {
            $seen = [];

            foreach ($houses as $house) {
                $listing = $listings->get($house->listing_key);

                if (! $listing instanceof MlsListing) {
                    continue;
                }

                $view = $this->listings->view($listing, ListingPurpose::Agent, true);
                $address = $view['address'] ?? (string) $listing->city;
                $event = CalendarEvent::withTrashed()->firstOrNew(['user_id' => $user->id, 'source' => $integration, 'external_id' => $house->open_house_key]);
                $event->forceFill([
                    'kind' => 'showing',
                    'title' => __($house->kind === 'broker' ? 'listings.calendar.brokerOpen' : 'listings.calendar.openHouse', ['address' => $address], $locale),
                    'location' => trim(implode(', ', array_filter([$listing->street, $listing->city, $listing->postal_code]))) ?: null,
                    'description' => $house->remarks,
                    'starts_at' => $house->starts_at,
                    'ends_at' => $house->ends_at,
                    'all_day' => false,
                    'busy' => false,
                    'status' => 'confirmed',
                    'related_type' => 'listing',
                    'related_id' => $listing->uuid,
                    'deleted_at' => null,
                ])->save();
                $seen[] = $house->open_house_key;
            }

            // Open houses the MLS cancelled, or that are no longer the agent's.
            CalendarEvent::query()->where('user_id', $user->id)->where('source', $integration)->where('ends_at', '>', now())
                ->whereNotIn('external_id', $seen)->forceDelete();
        });
    }
}
