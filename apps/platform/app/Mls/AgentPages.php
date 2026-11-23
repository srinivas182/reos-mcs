<?php

declare(strict_types=1);

namespace App\Mls;

use App\Deals\DealAccess;
use App\Enums\LicenceStatus;
use App\Models\AgentPage;
use App\Models\Licence;
use App\Models\MlsListing;
use App\Models\MlsMember;
use App\Models\MlsMemberLink;
use App\Models\MlsOffice;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The agent's public page (B10.2b): name, brokerage, license numbers, a
 * short bio and their MLS listings, each through the listing service for
 * public display (nothing stale, consumer flags, no sold prices unless the
 * MLS allows, its attribution on every listing). Off until the agent
 * publishes it; sample data is marked and kept out of search engines.
 *
 * @phpstan-import-type ListingView from ListingService
 *
 * @phpstan-type PageSettings array{handle: string, published: bool, bio: string|null, url: string}
 * @phpstan-type PublicPage array{name: string, brokerage: string|null, licences: list<array{state: string, number: string, type: string}>, bio: string|null, listings: list<ListingView>, sample: bool, url: string}
 */
final readonly class AgentPages
{
    public function __construct(private ListingService $listings, private DealAccess $access) {}

    /** @return PageSettings */
    public function settings(User $user): array
    {
        $this->access->allow($user);

        return $this->settingsOf($this->page($user));
    }

    /** @return PageSettings */
    public function update(User $user, bool $published, ?string $bio): array
    {
        $this->access->allow($user);
        $page = $this->page($user);
        $page->fill(['published' => $published, 'bio' => $bio === null || trim($bio) === '' ? null : trim($bio)])->save();

        return $this->settingsOf($page);
    }

    /** @return PublicPage|null null when there's no such published page */
    public function public(string $handle): ?array
    {
        $page = AgentPage::query()->where('handle', $handle)->where('published', true)->with('user')->first();

        if ($page === null) {
            return null;
        }

        $user = $page->user;
        $links = MlsMemberLink::query()->where('user_id', $user->id)->where('status', MlsMemberLink::CONFIRMED)->get();
        $listings = [];
        $brokerage = null;

        foreach ($links as $link) {
            $member = MlsMember::query()->where('integration', $link->integration)->where('member_key', $link->member_key)->first();
            $brokerage ??= $member?->office_key === null ? null : MlsOffice::query()->where('integration', $link->integration)->where('office_key', $member->office_key)->value('name');

            $rows = MlsListing::query()->where('integration', $link->integration)
                ->where(static fn ($q) => $q->where('list_agent_key', $link->member_key)->orWhere('co_list_agent_key', $link->member_key))
                ->orderByDesc('modified_at')->limit(60)->get();

            foreach ($rows->sortBy(static fn (MlsListing $l): int => (int) array_search($l->status, MlsListing::ORDER, true)) as $listing) {
                $view = $this->listings->view($listing, ListingPurpose::Public, true);

                if ($view !== null) {
                    $listings[] = $view;
                }
            }
        }

        $licences = [];

        foreach (Licence::query()->where('user_id', $user->id)->where('status', LicenceStatus::Active)->orderBy('state_code')->get() as $licence) {
            $licences[] = ['state' => $licence->state_code, 'number' => $licence->number, 'type' => $licence->type->value];
        }

        return [
            'name' => (string) $user->name,
            'brokerage' => is_string($brokerage) ? $brokerage : null,
            'licences' => $licences,
            'bio' => $page->bio,
            'listings' => $listings,
            'sample' => array_any($listings, static fn (array $l): bool => $l['sample']),
            'url' => route('agent-pages.show', $page->handle),
        ];
    }

    private function page(User $user): AgentPage
    {
        return AgentPage::query()->firstOrCreate(['user_id' => $user->id], ['handle' => $this->handleFor($user), 'published' => false]);
    }

    /** Paths under /agent that aren't public pages. */
    public const RESERVED = ['setup', 'mode', 'plan', 'brief'];

    /** "james-miller", or "james-miller-2" when that's taken. */
    private function handleFor(User $user): string
    {
        $base = Str::limit(Str::slug($user->name ?? ''), 60, '') ?: 'agent';
        $base = in_array($base, self::RESERVED, true) ? "{$base}-agent" : $base;
        $handle = $base;

        for ($n = 2; AgentPage::query()->where('handle', $handle)->exists(); $n++) {
            $handle = "{$base}-{$n}";
        }

        return $handle;
    }

    /** @return PageSettings */
    private function settingsOf(AgentPage $page): array
    {
        return ['handle' => $page->handle, 'published' => $page->published, 'bio' => $page->bio, 'url' => route('agent-pages.show', $page->handle)];
    }
}
