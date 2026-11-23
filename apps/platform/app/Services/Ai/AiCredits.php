<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Billing\ProductKind;
use App\Billing\SubscriptionStatus;
use App\Models\LedgerAccount;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\SponsorCodeRedemption;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * AI credits, read from the ledger (B5.3; the B4.3 stub's interface kept).
 * Each person's seat gets its plan's monthly credits (the largest of their
 * active plans and sponsored seats, else `reos.ai.monthly_credits`), which
 * expire at month end; top-up packs never expire. Spending takes the
 * month's credits first, then top-ups. **Candidates are never charged**
 * (screen 67): their AI use draws on a sponsor's credit pool, else the
 * platform's. Every call is also logged by feature and engine, never its
 * content (`ai_credit_uses`).
 */
final readonly class AiCredits
{
    public function __construct(private Ledger $ledger, private ActiveRole $roles, private Memberships $memberships, private AiSettings $settings) {}

    /** The person's monthly credits. */
    public function allowanceFor(User $user): int
    {
        $products = Product::query()->whereNotNull('credits')->where('kind', '!=', ProductKind::CreditPack)
            ->where(static fn ($q) => $q
                ->whereIn('id', Subscription::query()->select('product_id')->where('user_id', $user->id)->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue]))
                ->orWhereIn('id', SponsorCodeRedemption::query()->join('sponsor_codes', 'sponsor_codes.id', '=', 'sponsor_code_redemptions.sponsor_code_id')
                    ->where('sponsor_code_redemptions.user_id', $user->id)->select('sponsor_codes.product_id')))
            ->max('credits');

        return is_numeric($products) ? (int) $products : $this->allowance();
    }

    /** The default monthly allowance, for people without a plan that sets one. */
    public function allowance(): int
    {
        return max(0, $this->settings->current()['default_monthly_credits']);
    }

    /** The abuse limit (screen 69): AI actions per person per hour. */
    public function withinHourlyLimit(User $user): bool
    {
        $count = DB::table('ai_credit_uses')->where('user_id', $user->id)->where('created_at', '>=', CarbonImmutable::now()->subHour())->count();

        return $count < $this->settings->current()['hourly_limit'];
    }

    /** Credits spent this month. */
    public function used(User $user): int
    {
        return (int) DB::table('ai_credit_uses')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->sum('credits');
    }

    /** What's left: this month's credits plus top-ups. */
    public function left(User $user): int
    {
        $this->ensureMonth($user);

        return max(0, $this->ledger->balance($this->ledger->person($user, 'monthly')) + $this->ledger->balance($this->ledger->person($user, 'topup')));
    }

    /**
     * Credits this month and top-ups, for Plan and billing.
     *
     * @return array{monthly: int, topup: int, allowance: int, used: int, sponsored: bool}
     */
    public function summary(User $user): array
    {
        $this->ensureMonth($user);

        return [
            'monthly' => max(0, $this->ledger->balance($this->ledger->person($user, 'monthly'))),
            'topup' => max(0, $this->ledger->balance($this->ledger->person($user, 'topup'))),
            'allowance' => $this->allowanceFor($user),
            'used' => $this->used($user),
            'sponsored' => $this->isCandidate($user),
        ];
    }

    public function canSpend(User $user, int $credits = 1): bool
    {
        if (! $this->withinHourlyLimit($user)) {
            return false;
        }

        return $this->isCandidate($user) || $this->left($user) >= $credits;
    }

    /**
     * An AI action that costs no credits (CRM summaries for agents, B8.3):
     * logged at 0 so it still counts toward the hourly limit and AI usage.
     */
    public function logFree(User $user, string $feature, string $engine): void
    {
        DB::table('ai_credit_uses')->insert(['user_id' => $user->id, 'feature' => $feature, 'engine' => $engine, 'credits' => 0, 'created_at' => CarbonImmutable::now()]);
    }

    public function charge(User $user, string $feature, string $engine, int $credits = 1): void
    {
        DB::transaction(function () use ($user, $feature, $engine, $credits): void {
            $used = $this->ledger->platform('ai_used');

            if ($this->isCandidate($user)) {
                // Never the candidate: their sponsor's pool, else the platform's.
                $pool = $this->sponsorPool($user, $credits) ?? $this->ledger->platform('ai_pool');
                $this->ledger->post('ai_use', [[$pool, -$credits], [$used, $credits]], 'user', (string) $user->id, $feature);
            } else {
                $this->ensureMonth($user);
                $monthly = $this->ledger->person($user, 'monthly');
                $fromMonth = min($credits, max(0, $this->ledger->balance($monthly)));
                $this->ledger->post('ai_use', [
                    [$monthly, -$fromMonth],
                    [$this->ledger->person($user, 'topup'), -($credits - $fromMonth)],
                    [$used, $credits],
                ], 'user', (string) $user->id, $feature);
            }

            DB::table('ai_credit_uses')->insert([
                'user_id' => $user->id,
                'feature' => $feature,
                'engine' => $engine,
                'credits' => $credits,
                'created_at' => CarbonImmutable::now(),
            ]);
        });
    }

    /** A school's credits (its pool, funded on the tenant's Plan tab), for AI lessons (A7.3). */
    public function schoolLeft(Organisation $school): int
    {
        $pool = $this->ledger->find("organisation:{$school->id}:credits");

        return $pool === null ? 0 : max(0, $this->ledger->balance($pool));
    }

    /**
     * AI lessons are charged to the school that owns the course (A7.3), never
     * the instructor; the use is logged under the person who asked.
     */
    public function chargeSchool(Organisation $school, User $actor, string $feature, string $engine, int $credits, string $reference): void
    {
        if ($credits <= 0 || $this->ledger->posted('ai_use', 'lesson_version', $reference)) {
            return;
        }

        DB::transaction(function () use ($school, $actor, $feature, $engine, $credits, $reference): void {
            $this->ledger->post('ai_use', [[$this->ledger->organisation($school, 'credits'), -$credits], [$this->ledger->platform('ai_used'), $credits]], 'lesson_version', $reference, $feature);
            DB::table('ai_credit_uses')->insert([
                'user_id' => $actor->id, 'feature' => $feature, 'engine' => $engine, 'credits' => $credits, 'created_at' => CarbonImmutable::now(),
            ]);
        });
    }

    /** A top-up pack (never expires). */
    public function topUp(User $user, int $credits, string $orderUuid): void
    {
        if ($credits <= 0 || $this->ledger->posted('topup', 'order', $orderUuid)) {
            return;
        }

        $this->ledger->post('topup', [[$this->ledger->platform('credits_sold'), -$credits], [$this->ledger->person($user, 'topup'), $credits]], 'order', $orderUuid);
    }

    /**
     * The month's credits: last month's leftovers expire, this month's arrive.
     * Once per person and month, even when two requests arrive together.
     */
    public function ensureMonth(User $user): void
    {
        $month = CarbonImmutable::now()->format('Y-m');
        $reference = $user->id.':'.$month;

        if ($this->ledger->posted('monthly_grant', 'credit_month', $reference)) {
            return;
        }

        DB::transaction(function () use ($user, $reference): void {
            $monthly = $this->ledger->person($user, 'monthly');
            LedgerAccount::query()->whereKey($monthly->id)->lockForUpdate()->first();

            if ($this->ledger->posted('monthly_grant', 'credit_month', $reference)) {
                return;
            }

            $pool = $this->ledger->platform('ai_pool');
            $left = $this->ledger->balance($monthly);

            if ($left > 0) {
                $this->ledger->post('monthly_expired', [[$monthly, -$left], [$pool, $left]], 'credit_month', $reference);
            }

            $allowance = $this->allowanceFor($user);
            // Posted even when zero, so the month counts as granted.
            $this->ledger->post('monthly_grant', [[$pool, -$allowance], [$monthly, $allowance]], 'credit_month', $reference, keepZero: true);
        });
    }

    private function isCandidate(User $user): bool
    {
        return $this->roles->for($user)?->role === Role::Candidate;
    }

    /** A sponsor of this person (their school, brokerage or association) with enough credits in its pool. */
    private function sponsorPool(User $user, int $credits): ?LedgerAccount
    {
        $sponsor = $this->sponsorFor($user, $credits);

        return $sponsor === null ? null : $this->ledger->find("organisation:{$sponsor->id}:credits");
    }

    /**
     * The organisation paying for a candidate's AI beyond their course (A10.1,
     * screen k11): their school, brokerage or association with credits in its
     * pool. Null when nobody sponsors them.
     */
    public function sponsorFor(User $user, int $credits = 1): ?Organisation
    {
        foreach ($this->memberships->of($user) as $membership) {
            $organisation = $membership->organisation;
            $pool = $this->ledger->find("organisation:{$organisation->id}:credits");

            if ($pool !== null && $this->ledger->balance($pool) >= $credits) {
                return $organisation;
            }
        }

        return null;
    }

    /** Whether the person is a candidate (their AI is never charged to them). */
    public function forCandidate(User $user): bool
    {
        return $this->isCandidate($user);
    }
}
