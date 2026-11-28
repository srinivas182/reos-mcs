<?php

declare(strict_types=1);

namespace App\Mls;

use App\Authorization\Role;
use App\Compliance\ComplianceEngine;
use App\Compliance\ComplianceLog;
use App\Compliance\TexasAdRules;
use App\Deals\ClientPortals;
use App\Deals\DealAccess;
use App\Enums\LicenceStatus;
use App\Feedback\AgentFeedbacks;
use App\Models\AgentPage;
use App\Models\Licence;
use App\Models\MlsListing;
use App\Models\MlsMember;
use App\Models\MlsMemberLink;
use App\Models\MlsOffice;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Studio\AdvertisingPolicy;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The agent's public page (B10.2b): name, brokerage, license numbers, a
 * short bio and their MLS listings, each through the listing service for
 * public display (nothing stale, consumer flags, no sold prices unless the
 * MLS allows, its attribution on every listing). Off until the agent
 * publishes it; sample data is marked and kept out of search engines.
 *
 * @phpstan-import-type ListingView from ListingService
 *
 * @phpstan-type PageSettings array{handle: string, published: bool, bio: string|null, url: string, waiting: bool, note: string|null, phone: string|null, email: string|null, areas: list<string>, card_url: string, vcard_url: string, qr_url: string}
 * @phpstan-type PublicPage array{handle: string, name: string, brokerage: string|null, licences: list<array{state: string, number: string, type: string}>, bio: string|null, peer_rating: array{average: float, count: int}|null, listings: list<ListingView>, sample: bool, url: string, phone: string|null, email: string|null, areas: list<string>, language: 'en'|'es', updated_at: string|null, client_reviews: array{average: float, count: int, latest: list<array{name: string, rating: int, comment: string|null, date: string}>}|null}
 */
final readonly class AgentPages
{
    public function __construct(private ListingService $listings, private DealAccess $access, private AgentFeedbacks $feedback, private ComplianceEngine $compliance, private ComplianceLog $log, private AdvertisingPolicy $policy, private Memberships $memberships, private AuditLogger $audit, private ClientPortals $portals) {}

    /** The broker's name the page shows: the agent's MLS office, else their brokerage or sponsoring broker. */
    private function brokerageShown(User $user): ?string
    {
        foreach (MlsMemberLink::query()->where('user_id', $user->id)->where('status', MlsMemberLink::CONFIRMED)->get() as $link) {
            $member = MlsMember::query()->where('integration', $link->integration)->where('member_key', $link->member_key)->first();
            $office = $member?->office_key === null ? null : MlsOffice::query()->where('integration', $link->integration)->where('office_key', $member->office_key)->value('name');

            if (is_string($office)) {
                return $office;
            }
        }

        return $this->compliance->brokerName($user);
    }

    /** @return PageSettings */
    public function settings(User $user): array
    {
        $this->access->allow($user);

        return $this->settingsOf($this->page($user));
    }

    /**
     * @param  array{phone?: string|null, email?: string|null, areas?: list<string>}  $card  the card's details (B14.1), when sent
     * @return PageSettings
     */
    public function update(User $user, bool $published, ?string $bio, array $card = []): array
    {
        $this->access->allow($user);
        $page = $this->page($user);
        $bio = $bio === null || trim($bio) === '' ? null : trim($bio);

        // The compliance engine (B13.1): a published page names the broker, links the IABS and
        // Consumer Protection Notice (always on the page), and its bio passes the wording checks.
        if ($published) {
            $shown = $this->brokerageShown($user);
            $result = $this->compliance->check($user, ['channel' => 'page', 'text' => (string) $bio, 'attribution' => $shown, 'broker' => $shown, 'links' => [TexasAdRules::IABS, TexasAdRules::CPN]]);

            if (! $result['ok']) {
                $this->log->record($user, 'page', 'blocked', $result['issues'], subjectType: 'agent_page', subjectId: $page->handle);

                throw ValidationException::withMessages(['bio' => array_values(array_map(static function (array $i): string {
                    $line = __('compliance.blocked.'.$i['code'], ['phrase' => $i['phrase'] ?? '', 'citation' => $i['citation']]);

                    return is_string($line) ? $line : $i['code'];
                }, array_filter($result['issues'], static fn (array $i): bool => $i['severity'] === 'block')))]);
            }
        }

        // Brokerage rules on every channel (B13.2): when the brokerage approves its agents' ads, the page
        // waits for the broker before it goes live; an unchanged live page stays live.
        $waits = $published && $this->policy->needsApproval($user) && ! ($page->published && $page->bio === $bio);

        // The card's details (B14.1): what the agent chose to show; nothing taken from the account.
        foreach (['phone', 'email'] as $key) {
            if (array_key_exists($key, $card)) {
                $page->{$key} = $card[$key];
            }
        }

        if (array_key_exists('areas', $card)) {
            $page->areas = $card['areas'] === [] ? null : $card['areas'];
        }

        $page->fill(['published' => $published && ! $waits, 'bio' => $bio])->forceFill([
            'approval_requested_at' => $waits ? now() : ($published ? null : $page->approval_requested_at),
            'approval_note' => $waits ? null : $page->approval_note,
        ])->save();

        if ($published) {
            $this->log->record($user, 'page', $waits ? 'sent_to_broker' : 'checked', $result['issues'], subjectType: 'agent_page', subjectId: $page->handle);
        }

        return $this->settingsOf($page);
    }

    /**
     * Pages waiting for the broker (B13.2).
     *
     * @return list<array{id: string, agent: string, bio: string|null, url: string, requested_at: string|null}>
     */
    public function pending(Organisation $brokerage): array
    {
        $agents = $this->memberships->holdersInTree(Role::Agent, $brokerage)->pluck('id')->all();

        return array_values(AgentPage::query()->whereIn('user_id', $agents)->whereNotNull('approval_requested_at')->with('user')->orderBy('approval_requested_at')->limit(100)->get()
            ->map(static fn (AgentPage $p): array => ['id' => $p->handle, 'agent' => (string) $p->user->name, 'bio' => $p->bio, 'url' => route('agent-pages.show', $p->handle), 'requested_at' => $p->approval_requested_at?->toIso8601String()])->all());
    }

    /** The broker approves a waiting page (it goes live) or sends it back with a note. */
    public function decide(User $broker, Organisation $brokerage, string $handle, bool $approve, ?string $note = null): void
    {
        $agents = $this->memberships->holdersInTree(Role::Agent, $brokerage)->pluck('id')->all();
        $page = AgentPage::query()->where('handle', $handle)->whereIn('user_id', $agents)->whereNotNull('approval_requested_at')->with('user')->firstOrFail();
        $page->forceFill(['published' => $approve, 'approval_requested_at' => null, 'approval_note' => $approve ? null : mb_substr(trim((string) $note), 0, 500)])->save();
        $this->audit->record($approve ? 'agent_page.approved' : 'agent_page.returned', 'agent_page', $page->handle, ['agent' => $page->user_id], $broker, $brokerage->id);
        $this->log->record($page->user, 'page', $approve ? 'approved' : 'sent_back', [], $broker, 'agent_page', $page->handle, $approve ? null : $page->approval_note);
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
            'handle' => $page->handle,
            'name' => (string) $user->name,
            // The broker's name on every page (22 TAC §535.155): the MLS office, else the agent's brokerage.
            'brokerage' => is_string($brokerage) ? $brokerage : $this->compliance->brokerName($user),
            'licences' => $licences,
            'bio' => $page->bio,
            // Feedback from agents, only if the agent chose to show it (A13.3b).
            'peer_rating' => $this->feedback->publicRating($user),
            'listings' => $listings,
            'sample' => array_any($listings, static fn (array $l): bool => $l['sample']),
            'url' => route('agent-pages.show', $page->handle),
            'phone' => $page->phone,
            'email' => $page->email,
            // The areas the agent named, else the cities of their listings.
            'areas' => $page->areas ?? array_slice(array_values(array_unique(array_filter(array_map(static fn (array $l): string => (string) $l['city'], $listings)))), 0, 6),
            'language' => $user->locale->value === 'es' ? 'es' : 'en',
            'updated_at' => $page->updated_at?->toIso8601String(),
            // Reviews clients chose to show, from their portal after closing (B14.2).
            'client_reviews' => $this->portals->publicReviews($user),
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
        return [
            'handle' => $page->handle, 'published' => $page->published, 'bio' => $page->bio, 'url' => route('agent-pages.show', $page->handle),
            // Waiting for the broker, and what the broker said when sending it back (B13.2).
            'waiting' => $page->approval_requested_at !== null, 'note' => $page->approval_note,
            // The digital card (B14.1).
            'phone' => $page->phone, 'email' => $page->email, 'areas' => $page->areas ?? [],
            'card_url' => route('agent-pages.card', $page->handle),
            'vcard_url' => route('agent-pages.vcard', $page->handle),
            'qr_url' => route('agent-pages.qr', $page->handle),
        ];
    }
}
