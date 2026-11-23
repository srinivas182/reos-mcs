<?php

declare(strict_types=1);

namespace App\Billing;

use App\Models\PlatformSetting;
use App\Models\Price;
use App\Models\User;
use App\Notifications\MembershipLink;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Services\States\PersonStates;

/**
 * The phone's Membership screen (B5.1b; candidate screen 6, decision 4). The
 * app never shows a price or sells. On the iPhone it offers "Email me the
 * link" or, when the super admin switches it on, a link to reos.com;
 * Android shows the same words without a link. A sponsor's code unlocks a
 * seat in the app.
 */
final readonly class Membership
{
    public const IOS_BUY_LINK = 'membership.ios_buy_link';

    public const EMAIL = 'email';

    public const LINK = 'link';

    public function __construct(
        private Entitlements $entitlements,
        private PersonStates $states,
        private ActiveRole $roles,
        private AuditLogger $audit,
    ) {}

    /** How the iPhone app points to buying: `email` (default) or `link`. */
    public function iosBuyLink(): string
    {
        $value = PlatformSetting::query()->find(self::IOS_BUY_LINK)?->value['mode'] ?? null;

        return $value === self::LINK ? self::LINK : self::EMAIL;
    }

    public function setIosBuyLink(string $mode, User $actor): void
    {
        $from = $this->iosBuyLink();
        PlatformSetting::query()->updateOrCreate(['key' => self::IOS_BUY_LINK], ['value' => ['mode' => $mode], 'updated_by' => $actor->id]);

        if ($from !== $mode) {
            $this->audit->record('platform.setting_changed', 'platform_setting', self::IOS_BUY_LINK, ['from' => $from, 'to' => $mode], $actor);
        }
    }

    /**
     * What the Membership screen shows: whether the person has access, what
     * a plan includes (never the price), and how the iPhone points to buying.
     *
     * @return array{active: bool, features: list<string>, includes: list<string>, ios_buy_link: string, buy_url: string|null}
     */
    public function summary(User $user): array
    {
        $state = $this->states->current($user);
        $buyer = Buyer::forRole($this->roles->for($user)?->role) ?? Buyer::Candidate;
        $features = $this->entitlements->features($user, $state);
        $mode = $this->iosBuyLink();

        $plan = Price::query()->with('product')->current()->where('state_code', $state)
            ->whereHas('product', static fn ($q) => $q->where('buyer', $buyer)->where('status', ProductStatus::Live))
            ->orderByDesc('amount_cents')
            ->first();

        return [
            'active' => $features !== [],
            'features' => $features,
            'includes' => $plan?->product->includes ?? [],
            'ios_buy_link' => $mode,
            // Candidates choose a package on reos.com (A8.3b); everyone else goes to Plan and billing.
            'buy_url' => $mode !== self::LINK ? null : ($buyer === Buyer::Candidate ? route('packages', ['code' => $state]) : route('billing.index')),
        ];
    }

    /** "Email me the link": the plans page, sent to the person's email, to open on a computer or the phone's browser. */
    public function emailLink(User $user): bool
    {
        if ($user->email === null) {
            return false;
        }

        $user->notify(new MembershipLink);

        return true;
    }
}
