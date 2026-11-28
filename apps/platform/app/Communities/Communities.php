<?php

declare(strict_types=1);

namespace App\Communities;

use App\Authorization\Role;
use App\Billing\Buyer;
use App\Billing\Payouts\PayeeRole;
use App\Billing\PriceInterval;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Enums\StateStatus;
use App\Models\Community;
use App\Models\CommunityEvent;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityResource;
use App\Models\CommunityRsvp;
use App\Models\Order;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductSplit;
use App\Models\ProviderProfile;
use App\Models\State;
use App\Models\User;
use App\Notifications\CommunitySessionPosted;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Communities (B17.2, ADR 0086; provider screens 16–18).
 *
 * - **Led by Pro and Partner providers** (decision 5), one community each.
 * - **Verified members only:** agents in a brokerage on REOS, and live
 *   providers. They join by the community's link or QR code.
 * - **Posts** are checked before they go up: advertising wording, no pay
 *   for referrals (RESPA), no contacts or links.
 * - **Resources:** links the owner shares.
 * - **Expert sessions:** online or in person, with RSVP and a capacity.
 *   Free on Pro; a flat price on Partner only, paid through checkout. Each
 *   says clearly whether it counts for CE (the host's course number from
 *   an approval elsewhere), and is otherwise information only.
 * - **Results** for the owner: members, how they joined, posts, sessions,
 *   RSVPs and attendance.
 *
 * @phpstan-type SessionRow array{id: string, title: string, about: string|null, format: string, starts_at: string, minutes: int, place: string|null, join_url: string|null, capacity: int|null, price_cents: int, ce_course: string|null, status: string, going: int, attended: int, mine: string|null}
 * @phpstan-type PostRow array{id: string, author: string, body: string, pinned: bool, at: string, mine: bool}
 * @phpstan-type View array{id: string, name: string, about: string|null, owner: string, is_owner: bool, member: bool, members: int, invite_url: string|null, qr_url: string|null, posts: list<PostRow>, resources: list<array{id: string, title: string, url: string}>, sessions: list<SessionRow>}
 */
final readonly class Communities
{
    public const TIERS = ['pro', 'premium'];

    public function __construct(private Memberships $memberships, private AuditLogger $audit, private Tenancy $tenancy) {}

    /** The owner's community, set up on first use (Pro or Partner only). */
    public function forOwner(User $owner): Community
    {
        $profile = $this->leading($owner);

        return Community::query()->where('owner_id', $owner->id)->first() ?? DB::transaction(function () use ($owner, $profile): Community {
            $community = Community::query()->create([
                'uuid' => (string) Str::uuid7(), 'owner_id' => $owner->id, 'invite_code' => self::code(),
                'name' => mb_substr((string) ($profile->business_name ?? $owner->name), 0, 120),
            ]);
            CommunityMember::query()->create(['community_id' => $community->id, 'user_id' => $owner->id, 'role' => 'owner', 'via' => 'owner', 'joined_at' => now()]);
            $this->record('community.created', $community->uuid, [], $owner, $profile->organisation_id);

            return $community;
        });
    }

    public function update(User $owner, string $name, ?string $about): Community
    {
        $community = $this->forOwner($owner);
        $issues = PostCheck::issues($name.' '.($about ?? ''));

        if ($issues !== []) {
            throw ValidationException::withMessages(['about' => self::message($issues)]);
        }

        $community->forceFill(['name' => $name, 'about' => $about])->save();

        return $community;
    }

    /** A new link and QR code; the old one stops working. */
    public function newCode(User $owner): Community
    {
        $community = $this->forOwner($owner);
        $community->forceFill(['invite_code' => self::code()])->save();
        $this->record('community.code_changed', $community->uuid, [], $owner, null);

        return $community;
    }

    /** Partner providers can charge for sessions (decision 5). */
    public function canCharge(User $owner): bool
    {
        $profile = $this->leading($owner);

        return $profile->tier === 'premium' && $profile->organisation_id !== null;
    }

    /** Verified: an agent in a brokerage on REOS, or a live provider. */
    public function verified(User $user): bool
    {
        foreach ($this->memberships->of($user) as $m) {
            if (in_array($m->role, [Role::Agent, Role::Broker], true)) {
                return true;
            }
        }

        return ProviderProfile::query()->where('user_id', $user->id)->where('status', ProviderProfile::LIVE)->exists();
    }

    /**
     * What the invite link shows before joining.
     *
     * @return array{id: string, name: string, about: string|null, owner: string, members: int, verified: bool, member: bool}
     */
    public function preview(User $user, string $code): array
    {
        $community = Community::query()->where('invite_code', $code)->with('owner')->first() ?? throw new NotFoundHttpException;

        return [
            'id' => $community->uuid, 'name' => $community->name, 'about' => $community->about, 'owner' => (string) $community->owner->name,
            'members' => self::memberCount($community), 'verified' => $this->verified($user), 'member' => $this->membership($community, $user) !== null,
        ];
    }

    public function join(User $user, string $code, string $via): Community
    {
        $community = Community::query()->where('invite_code', $code)->first() ?? throw new NotFoundHttpException;

        if (! $this->verified($user)) {
            throw ValidationException::withMessages(['code' => __('communities.errors.notVerified')]);
        }

        $member = CommunityMember::query()->firstOrNew(['community_id' => $community->id, 'user_id' => $user->id]);

        if (! $member->exists || $member->left_at !== null) {
            $member->forceFill(['role' => $member->role ?? 'member', 'via' => in_array($via, ['link', 'qr'], true) ? $via : 'link', 'joined_at' => now(), 'left_at' => null])->save();
            $this->record('community.joined', $community->uuid, ['via' => $member->via], $user, null);
        }

        return $community;
    }

    public function leave(User $user, string $uuid): void
    {
        $community = $this->find($uuid);
        $member = $this->membership($community, $user);

        if ($member === null || $member->role === 'owner') {
            throw new NotFoundHttpException;
        }

        $member->forceFill(['left_at' => now()])->save();
    }

    /**
     * Communities the person is in.
     *
     * @return list<array{id: string, name: string, owner: string, members: int, next: array{title: string, starts_at: string}|null}>
     */
    public function mine(User $user): array
    {
        $out = [];

        foreach (Community::query()->whereHas('members', static fn ($q) => $q->where('user_id', $user->id)->whereNull('left_at'))->with('owner')->orderBy('name')->get() as $c) {
            $next = CommunityEvent::query()->where('community_id', $c->id)->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->first();
            $out[] = ['id' => $c->uuid, 'name' => $c->name, 'owner' => (string) $c->owner->name, 'members' => self::memberCount($c), 'next' => $next === null ? null : ['title' => $next->title, 'starts_at' => $next->starts_at->toIso8601String()]];
        }

        return $out;
    }

    /** @return View */
    public function view(User $user, string $uuid): array
    {
        $community = $this->find($uuid)->load('owner');
        $member = $this->membership($community, $user) ?? throw new NotFoundHttpException;
        $owner = $member->role === 'owner';
        $posts = [];

        foreach (CommunityPost::query()->where('community_id', $community->id)->with('author')->orderByDesc('pinned')->latest('id')->limit(50)->get() as $p) {
            $posts[] = ['id' => $p->uuid, 'author' => (string) $p->author->name, 'body' => $p->body, 'pinned' => $p->pinned, 'at' => $p->created_at?->toIso8601String() ?? '', 'mine' => $p->author_id === $user->id];
        }

        $resources = [];

        foreach (CommunityResource::query()->where('community_id', $community->id)->orderBy('title')->get() as $r) {
            $resources[] = ['id' => $r->uuid, 'title' => $r->title, 'url' => $r->url];
        }

        $sessions = [];

        foreach (CommunityEvent::query()->where('community_id', $community->id)->where('starts_at', '>=', now()->subDays($owner ? 90 : 1))->orderBy('starts_at')->get() as $e) {
            $sessions[] = $this->sessionRow($e, $user);
        }

        return [
            'id' => $community->uuid, 'name' => $community->name, 'about' => $community->about, 'owner' => (string) $community->owner->name, 'is_owner' => $owner, 'member' => true,
            'members' => self::memberCount($community), 'invite_url' => $owner ? self::inviteUrl($community) : null, 'qr_url' => $owner ? route('community.qr') : null,
            'posts' => $posts, 'resources' => $resources, 'sessions' => $sessions,
        ];
    }

    public function post(User $user, string $uuid, string $body): CommunityPost
    {
        $community = $this->find($uuid);
        $this->membership($community, $user) ?? throw new NotFoundHttpException;
        $issues = PostCheck::issues($body);

        if ($issues !== []) {
            throw ValidationException::withMessages(['body' => self::message($issues)]);
        }

        return CommunityPost::query()->create(['uuid' => (string) Str::uuid7(), 'community_id' => $community->id, 'author_id' => $user->id, 'body' => trim($body)]);
    }

    /** The author or the owner takes a post down; the owner pins. */
    public function removePost(User $user, string $postUuid): void
    {
        $post = CommunityPost::query()->where('uuid', $postUuid)->first() ?? throw new NotFoundHttpException;
        $community = Community::query()->findOrFail($post->community_id);

        if ($post->author_id !== $user->id && $community->owner_id !== $user->id) {
            throw new NotFoundHttpException;
        }

        $post->delete();
        $this->record('community.post_removed', $community->uuid, ['author_id' => $post->author_id], $user, null);
    }

    public function pin(User $owner, string $postUuid, bool $pinned): void
    {
        $community = $this->forOwner($owner);
        $post = CommunityPost::query()->where('community_id', $community->id)->where('uuid', $postUuid)->first() ?? throw new NotFoundHttpException;
        $post->forceFill(['pinned' => $pinned])->save();
    }

    public function addResource(User $owner, string $title, string $url): CommunityResource
    {
        $community = $this->forOwner($owner);

        return CommunityResource::query()->create(['uuid' => (string) Str::uuid7(), 'community_id' => $community->id, 'title' => $title, 'url' => $url]);
    }

    public function removeResource(User $owner, string $uuid): void
    {
        CommunityResource::query()->where('community_id', $this->forOwner($owner)->id)->where('uuid', $uuid)->delete();
    }

    /**
     * Schedules an expert session and tells the members. Paid sessions are Partner only.
     *
     * @param  array{title: string, about: string|null, format: string, starts_at: CarbonImmutable, minutes: int, place: string|null, join_url: string|null, capacity: int|null, price_cents: int, ce_course: string|null}  $values
     */
    public function schedule(User $owner, array $values): CommunityEvent
    {
        $profile = $this->leading($owner);
        $community = $this->forOwner($owner);

        if ($values['price_cents'] > 0 && ($profile->tier !== 'premium' || $profile->organisation_id === null)) {
            throw ValidationException::withMessages(['price_cents' => __('communities.errors.paidPartner')]);
        }

        $issues = PostCheck::issues($values['title'].' '.($values['about'] ?? ''));

        if ($issues !== []) {
            throw ValidationException::withMessages(['about' => self::message($issues)]);
        }

        $event = DB::transaction(function () use ($owner, $community, $profile, $values): CommunityEvent {
            $event = CommunityEvent::query()->create([...$values, 'uuid' => (string) Str::uuid7(), 'community_id' => $community->id, 'host_id' => $owner->id, 'status' => 'scheduled']);

            if ($values['price_cents'] > 0 && $profile->organisation_id !== null) {
                $event->forceFill(['product_id' => $this->product($event, $profile->organisation_id)->id])->save();
            }

            return $event;
        });

        $members = User::query()->whereIn('id', CommunityMember::query()->where('community_id', $community->id)->whereNull('left_at')->where('user_id', '!=', $owner->id)->pluck('user_id'))->get();
        Notification::send($members, new CommunitySessionPosted($event->load('community')));
        $this->record('community.session_scheduled', $community->uuid, ['session' => $event->uuid, 'price_cents' => $event->price_cents], $owner, $profile->organisation_id);

        return $event;
    }

    public function cancelSession(User $owner, string $uuid): void
    {
        $event = $this->ownEvent($owner, $uuid);
        $event->forceFill(['status' => 'cancelled'])->save();
        $this->record('community.session_cancelled', $event->community->uuid, ['session' => $event->uuid], $owner, null);
    }

    /**
     * RSVP. Free: going. Paid: held until checkout is paid.
     *
     * @return array{status: string, checkout_url: string|null}
     */
    public function rsvp(User $user, string $uuid): array
    {
        $event = CommunityEvent::query()->where('uuid', $uuid)->with('community')->first() ?? throw new NotFoundHttpException;
        $this->membership($event->community, $user) ?? throw new NotFoundHttpException;

        if ($event->status !== 'scheduled' || $event->starts_at->isPast()) {
            throw ValidationException::withMessages(['session' => __('communities.errors.closed')]);
        }

        $rsvp = CommunityRsvp::query()->firstOrNew(['community_event_id' => $event->id, 'user_id' => $user->id]);

        if ($rsvp->exists && in_array($rsvp->status, ['going', 'held'], true)) {
            return ['status' => $rsvp->status, 'checkout_url' => $rsvp->status === 'held' ? $this->checkoutUrl($event) : null];
        }

        $taken = CommunityRsvp::query()->where('community_event_id', $event->id)->whereIn('status', ['going', 'held'])->count();

        if ($event->capacity !== null && $taken >= $event->capacity) {
            throw ValidationException::withMessages(['session' => __('communities.errors.full')]);
        }

        $paid = $event->price_cents > 0;
        $rsvp->forceFill(['status' => $paid ? 'held' : 'going'])->save();

        return ['status' => $rsvp->status, 'checkout_url' => $paid ? $this->checkoutUrl($event) : null];
    }

    public function cancelRsvp(User $user, string $uuid): void
    {
        $event = CommunityEvent::query()->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;
        CommunityRsvp::query()->where('community_event_id', $event->id)->where('user_id', $user->id)->update(['status' => 'cancelled']);
    }

    /** The order for a paid session was paid: the held RSVP is going. Safe to call twice. */
    public function paid(Order $order): void
    {
        $event = CommunityEvent::query()->where('product_id', $order->product_id)->first();

        if ($event !== null && $order->user_id !== null) {
            CommunityRsvp::query()->updateOrCreate(['community_event_id' => $event->id, 'user_id' => $order->user_id], ['status' => 'going']);
        }
    }

    /**
     * Who came: the host ticks attendees after the session; it's then done.
     *
     * @param  list<int>  $userIds
     */
    public function attendance(User $owner, string $uuid, array $userIds): CommunityEvent
    {
        $event = $this->ownEvent($owner, $uuid);
        CommunityRsvp::query()->where('community_event_id', $event->id)->where('status', 'going')->whereIn('user_id', $userIds)->whereNull('attended_at')->update(['attended_at' => now()]);
        CommunityRsvp::query()->where('community_event_id', $event->id)->whereNotIn('user_id', $userIds)->update(['attended_at' => null]);
        $event->forceFill(['status' => 'done'])->save();
        $this->record('community.attendance', $event->community->uuid, ['session' => $event->uuid, 'attended' => count($userIds)], $owner, null);

        return $event;
    }

    /**
     * Who's going, for the host.
     *
     * @return list<array{id: int, name: string, attended: bool}>
     */
    public function roster(User $owner, string $uuid): array
    {
        $event = $this->ownEvent($owner, $uuid);
        $out = [];

        foreach (CommunityRsvp::query()->where('community_event_id', $event->id)->where('status', 'going')->with('user')->get()->sortBy('user.name') as $r) {
            $out[] = ['id' => $r->user_id, 'name' => (string) $r->user->name, 'attended' => $r->attended_at !== null];
        }

        return $out;
    }

    /**
     * Results for the owner (provider screen 18).
     *
     * @return array{members: int, joined_30: int, via: array{link: int, qr: int}, posts_30: int, sessions: int, rsvps: int, attended: int, show_rate: int, top: list<array{title: string, going: int, attended: int}>}
     */
    public function results(User $owner): array
    {
        $community = $this->forOwner($owner);
        $members = CommunityMember::query()->where('community_id', $community->id)->whereNull('left_at')->where('role', 'member');
        $events = CommunityEvent::query()->where('community_id', $community->id)->where('status', '!=', 'cancelled')->pluck('id');
        $rsvps = CommunityRsvp::query()->whereIn('community_event_id', $events)->where('status', 'going');
        $goingTotal = (clone $rsvps)->count();
        $attended = (clone $rsvps)->whereNotNull('attended_at')->count();
        $top = [];

        foreach (CommunityEvent::query()->whereIn('id', $events)->withCount([
            'rsvps as going_count' => static fn ($q) => $q->where('status', 'going'),
            'rsvps as attended_count' => static fn ($q) => $q->where('status', 'going')->whereNotNull('attended_at'),
        ])->orderByDesc('going_count')->limit(5)->get() as $e) {
            $going = $e->getAttribute('going_count');
            $came = $e->getAttribute('attended_count');
            $top[] = ['title' => $e->title, 'going' => is_numeric($going) ? (int) $going : 0, 'attended' => is_numeric($came) ? (int) $came : 0];
        }

        return [
            'members' => (clone $members)->count(), 'joined_30' => (clone $members)->where('joined_at', '>=', now()->subDays(30))->count(),
            'via' => ['link' => (clone $members)->where('via', 'link')->count(), 'qr' => (clone $members)->where('via', 'qr')->count()],
            'posts_30' => CommunityPost::query()->where('community_id', $community->id)->where('created_at', '>=', now()->subDays(30))->count(),
            'sessions' => $events->count(), 'rsvps' => $goingTotal, 'attended' => $attended, 'show_rate' => $goingTotal === 0 ? 0 : (int) round(100 * $attended / $goingTotal),
            'top' => $top,
        ];
    }

    /** The QR code to join, as SVG: the invite link marked as scanned. */
    public function qr(User $owner): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd)))->writeString(self::inviteUrl($this->forOwner($owner)).'?via=qr');
    }

    public static function inviteUrl(Community $community): string
    {
        return route('communities.join', $community->invite_code);
    }

    /** @return SessionRow */
    private function sessionRow(CommunityEvent $e, User $user): array
    {
        $mine = CommunityRsvp::query()->where('community_event_id', $e->id)->where('user_id', $user->id)->value('status');
        $going = CommunityRsvp::query()->where('community_event_id', $e->id)->where('status', 'going');

        return [
            'id' => $e->uuid, 'title' => $e->title, 'about' => $e->about, 'format' => $e->format, 'starts_at' => $e->starts_at->toIso8601String(), 'minutes' => $e->minutes,
            'place' => $e->place, 'join_url' => $mine === 'going' || $e->host_id === $user->id ? $e->join_url : null, 'capacity' => $e->capacity, 'price_cents' => $e->price_cents,
            'ce_course' => $e->ce_course, 'status' => $e->status, 'going' => (clone $going)->count(), 'attended' => (clone $going)->whereNotNull('attended_at')->count(),
            'mine' => is_string($mine) ? $mine : null,
        ];
    }

    private function checkoutUrl(CommunityEvent $event): ?string
    {
        $price = $event->product_id === null ? null : Price::query()->where('product_id', $event->product_id)->orderBy('id')->first();

        return $price === null ? null : route('checkout.show', $price);
    }

    /** A product for a paid session, sold by the provider's business: the provider is paid it all (a flat plan, no fee per payment). */
    private function product(CommunityEvent $event, int $business): Product
    {
        $product = Product::query()->create([
            'key' => 'community-session-'.$event->uuid, 'name' => mb_substr($event->title, 0, 120), 'kind' => ProductKind::Session, 'buyer' => Buyer::Agent,
            'seller_organisation_id' => $business, 'status' => ProductStatus::Live,
        ]);
        ProductSplit::query()->create(['product_id' => $product->id, 'payee_user_id' => $event->host_id, 'role' => PayeeRole::Seller, 'percent' => 100]);

        foreach (State::query()->where('status', StateStatus::Live)->pluck('code') as $code) {
            Price::query()->create(['product_id' => $product->id, 'state_code' => $code, 'interval' => PriceInterval::Once, 'amount_cents' => $event->price_cents, 'currency' => 'usd', 'starts_at' => now(), 'ends_at' => null]);
        }

        return $product;
    }

    /**
     * Communities span tenants, so their audit events are written at platform level.
     *
     * @param  array<string, mixed>  $properties
     */
    private function record(string $action, string $uuid, array $properties, ?User $actor, ?int $organisationId): void
    {
        $this->tenancy->asPlatform(fn () => $this->audit->record($action, 'community', $uuid, $properties, $actor, $organisationId));
    }

    private function leading(User $owner): ProviderProfile
    {
        $profile = ProviderProfile::query()->where('user_id', $owner->id)->where('status', ProviderProfile::LIVE)->first();

        if ($profile === null || ! in_array($profile->tier, self::TIERS, true)) {
            throw ValidationException::withMessages(['plan' => __('communities.errors.plan')]);
        }

        return $profile;
    }

    private function ownEvent(User $owner, string $uuid): CommunityEvent
    {
        return CommunityEvent::query()->where('uuid', $uuid)->where('host_id', $owner->id)->with('community')->first() ?? throw new NotFoundHttpException;
    }

    private function find(string $uuid): Community
    {
        return Community::query()->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;
    }

    private function membership(Community $community, User $user): ?CommunityMember
    {
        return CommunityMember::query()->where('community_id', $community->id)->where('user_id', $user->id)->whereNull('left_at')->first();
    }

    private static function memberCount(Community $community): int
    {
        return CommunityMember::query()->where('community_id', $community->id)->whereNull('left_at')->count();
    }

    /** @param list<array{code: string, phrase: string}> $issues */
    private static function message(array $issues): string
    {
        $first = $issues[0];
        $text = match ($first['code']) {
            'referral_pay' => __('communities.errors.referralPay', ['phrase' => $first['phrase']]),
            'contact' => __('communities.errors.contact'),
            default => __('communities.errors.wording', ['phrase' => $first['phrase']]),
        };

        return is_string($text) ? $text : '';
    }

    private static function code(): string
    {
        return Str::lower(Str::random(10));
    }
}
