<?php

declare(strict_types=1);

namespace App\Billing\Ledger;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Points (B5.3): earned for learning milestones and redeemable later for
 * sessions, credits, tickets or seats. B5.3 built the accounts and
 * entries; mentor sessions are paid with them from A13.1a (1 point = 1
 * cent inside REOS), and the earning rules are the daily goals (A13.3a). Each award is counted
 * once per milestone; agents never get cash for points.
 */
final readonly class Points
{
    public function __construct(private Ledger $ledger) {}

    public function balance(User $user): int
    {
        $account = $this->ledger->find("person:{$user->id}:points");

        return $account === null ? 0 : $this->ledger->balance($account);
    }

    /** Awards points once for a milestone (`$milestone` is unique per person, such as `course:12:done`). */
    public function award(User $user, int $points, string $milestone): bool
    {
        $reference = $user->id.':'.$milestone;

        if ($points <= 0 || $this->ledger->posted('points_awarded', 'milestone', $reference)) {
            return false;
        }

        $this->ledger->post('points_awarded', [[$this->ledger->platform('points_issued'), -$points], [$this->ledger->person($user, 'points'), $points]], 'milestone', $reference, $milestone);

        return true;
    }

    /** Spends points on something (`$for`, such as `session:44`); never more than the person has. */
    public function redeem(User $user, int $points, string $for): void
    {
        $account = $this->ledger->person($user, 'points');

        if ($points <= 0 || $this->ledger->balance($account) < $points) {
            throw ValidationException::withMessages(['points' => __('billing.points.notEnough')]);
        }

        $this->ledger->post('points_redeemed', [[$account, -$points], [$this->ledger->platform('points_redeemed'), $points]], 'redemption', $user->id.':'.$for, $for);
    }

    /** Gives spent points back (a refunded session); once per redemption. */
    public function giveBack(User $user, int $points, string $for): bool
    {
        $reference = $user->id.':'.$for;

        if ($points <= 0 || ! $this->ledger->posted('points_redeemed', 'redemption', $reference) || $this->ledger->posted('points_returned', 'redemption', $reference)) {
            return false;
        }

        $this->ledger->post('points_returned', [[$this->ledger->platform('points_redeemed'), -$points], [$this->ledger->person($user, 'points'), $points]], 'redemption', $reference, $for);

        return true;
    }

    /** Bonus points a brokerage funds (a campaign, A13.3a): once per milestone, from the brokerage's account. */
    public function bonus(Organisation $brokerage, User $user, int $points, string $milestone): bool
    {
        $reference = $user->id.':'.$milestone;

        if ($points <= 0 || $this->ledger->posted('points_bonus', 'milestone', $reference)) {
            return false;
        }

        $this->ledger->post('points_bonus', [[$this->ledger->organisation($brokerage, 'points'), -$points], [$this->ledger->person($user, 'points'), $points]], 'milestone', $reference, $milestone);

        return true;
    }
}
