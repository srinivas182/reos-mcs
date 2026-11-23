<?php

declare(strict_types=1);

namespace App\Deals;

use App\Models\DealDate;
use App\Models\User;
use App\Notifications\Centre\NotificationCentre;
use App\Notifications\DealDeadline;
use Carbon\CarbonImmutable;

/**
 * Deal deadlines, checked hourly (B9.1; web journey rule): a push at 72
 * hours and at 24 hours before each open key date. A key date is due at
 * 5:00 pm on its day in the agent's time zone, when Texas contract periods
 * such as the option period end. Each push goes once; a moved date can push
 * again.
 */
final readonly class Deadlines
{
    public const DUE_HOUR = 17;

    /** @return int Pushes sent. */
    public function check(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $sent = 0;

        $dates = DealDate::query()->whereNull('done_at')
            ->whereBetween('due_on', [$now->subDay()->toDateString(), $now->addDays(4)->toDateString()])
            ->where(static fn ($q) => $q->whereNull('alerted_72_at')->orWhereNull('alerted_24_at'))
            ->whereHas('deal', static fn ($q) => $q->whereIn('stage', ['active', 'under_contract']))
            ->with('deal.user')->get();

        foreach ($dates as $date) {
            $owner = $date->deal->user;
            $due = self::dueAt($date, $owner);
            $hours = $now->diffInHours($due, false);

            if ($hours < 0) {
                continue;
            }

            $window = $hours <= 24 && $date->alerted_24_at === null ? 24 : ($hours <= 72 && $date->alerted_72_at === null ? 72 : null);

            if ($window === null) {
                continue;
            }

            $owner->notify(new DealDeadline($date->deal->uuid, $date->deal->property, Deals::dateName($date, $owner), $due->toIso8601String(), $window));
            // Within 24 hours, the 72-hour push is no longer worth sending.
            $date->forceFill(['alerted_24_at' => $window === 24 ? $now : null, 'alerted_72_at' => $now])->save();
            $sent++;
        }

        return $sent;
    }

    public static function dueAt(DealDate $date, User $owner): CarbonImmutable
    {
        return CarbonImmutable::parse($date->due_on->toDateString().' '.self::DUE_HOUR.':00', self::zone($owner));
    }

    /** Today, in the agent's time zone. */
    public static function today(User $user): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone($user))->startOfDay();
    }

    private static function zone(User $user): string
    {
        return app(NotificationCentre::class)->settings($user)->timezone;
    }
}
