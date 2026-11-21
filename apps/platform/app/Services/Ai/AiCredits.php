<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AI credits (B4.3 stub; the full ledger, pools per seat and top-ups arrive
 * in Sprint 6). Each person has a monthly allowance (`reos.ai.monthly_credits`);
 * every AI call records its feature, engine and cost, never its content.
 * Out of credits, features fall back to what runs without AI.
 */
final readonly class AiCredits
{
    public function allowance(): int
    {
        return max(0, config()->integer('reos.ai.monthly_credits', 500));
    }

    public function used(User $user): int
    {
        $used = DB::table('ai_credit_uses')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('credits');

        return (int) $used;
    }

    public function left(User $user): int
    {
        return max(0, $this->allowance() - $this->used($user));
    }

    public function canSpend(User $user, int $credits = 1): bool
    {
        return $this->left($user) >= $credits;
    }

    public function charge(User $user, string $feature, string $engine, int $credits = 1): void
    {
        DB::table('ai_credit_uses')->insert([
            'user_id' => $user->id,
            'feature' => $feature,
            'engine' => $engine,
            'credits' => $credits,
            'created_at' => Carbon::now(),
        ]);
    }
}
