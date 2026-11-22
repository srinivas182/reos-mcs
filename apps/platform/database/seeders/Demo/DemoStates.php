<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\StateStatus;
use App\Models\State;
use App\Models\User;
use App\Models\WaitlistEntry;
use Ramsey\Uuid\Uuid;

/**
 * States as on web journey screen 61 (A5.1): Louisiana in preparation with
 * half its launch checklist done, and people waiting for Louisiana,
 * Florida, Oklahoma and Georgia. Safe to run again.
 */
final class DemoStates
{
    /** @var array<string, int> state => demo people waiting */
    private const WAITING = ['LA' => 12, 'FL' => 30, 'OK' => 6, 'GA' => 3];

    public function seed(): void
    {
        $ops = User::query()->where('email', 'ops@'.Personas::DOMAIN)->first();
        $louisiana = State::query()->findOrFail('LA');

        if ($louisiana->status !== StateStatus::Live) {
            $louisiana->forceFill([
                'status' => StateStatus::InPreparation,
                'regulator' => 'LREC',
                'exam_vendor' => 'PSI',
                'qualifying_hours' => 90,
                'ce_hours' => 12,
                'launch_checklist' => $ops === null ? null : array_fill_keys(['rules', 'approvals', 'advertising'], ['by' => $ops->id, 'at' => now()->toIso8601String()]),
            ])->save();
        }

        foreach (self::WAITING as $state => $count) {
            for ($i = 1; $i <= $count; $i++) {
                // The same made-up device each run, so running again adds no one.
                WaitlistEntry::query()->firstOrCreate(['state_code' => $state, 'device_id' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, "https://reos.com/demo/waitlist/{$state}/{$i}")]);
            }
        }
    }
}
