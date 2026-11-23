<?php

declare(strict_types=1);

namespace App\Licences\Ce;

use App\Models\CeReminder;
use App\Models\Licence;
use App\Notifications\CeRenewalDue;
use App\Services\StateRules\CeRequirements;
use Carbon\CarbonImmutable;

/**
 * CE renewal reminders (A11.2): 60, 30 and 7 days before the licence's
 * renewal date while hours are missing, and "Renewal ready" once they're
 * done (agent screen 106). Each goes once per licence period; a missed day
 * sends only the nearest one.
 */
final readonly class CeReminders
{
    /** Days before the renewal date, nearest first. */
    public const DAYS = [7, 30, 60];

    public function __construct(private CeRequirements $requirements) {}

    public function sendDue(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $sent = 0;

        Licence::query()->with('user')->whereNotNull('expires_on')->chunkById(200, function ($licences) use ($today, &$sent): void {
            foreach ($licences as $licence) {
                if (! $licence->status->counts()) {
                    continue;
                }

                $ce = $this->requirements->for($licence, $today);

                if ($ce === null || $ce['due_on'] === null || $ce['days_left'] === null || $ce['days_left'] < 0) {
                    continue;
                }

                $kind = null;

                if ($ce['complete']) {
                    $kind = 'ready';
                } else {
                    foreach (self::DAYS as $days) {
                        if ($ce['days_left'] <= $days) {
                            $kind = "days_{$days}";
                            break;
                        }
                    }
                }

                if ($kind === null) {
                    continue;
                }

                $reminder = CeReminder::query()->firstOrCreate(['licence_id' => $licence->id, 'due_on' => $ce['due_on'], 'kind' => $kind], ['sent_at' => now()]);

                if ($reminder->wasRecentlyCreated) {
                    $licence->user->notify(new CeRenewalDue($licence, $kind, $ce['hours_left'], $ce['due_on']));
                    $sent++;
                }
            }
        });

        return $sent;
    }
}
