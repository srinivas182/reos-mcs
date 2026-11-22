<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\User;
use App\Notifications\AccountDeletionScheduled;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\PrivacyRequests;
use Illuminate\Support\Facades\DB;

/**
 * Deleting an account (required by the App Store and Google Play): the
 * account is signed out everywhere and deleted after 30 days. Signing in
 * again before then keeps it.
 */
final readonly class AccountDeletion
{
    public const GRACE_DAYS = 30;

    public function __construct(private AuditLogger $audit, private PrivacyRequests $privacy) {}

    public function request(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['deletion_requested_at' => now()])->save();
            $user->tokens()->delete();

            $this->audit->record('account.deletion_requested', 'user', (string) $user->id, [
                'delete_after' => now()->addDays(self::GRACE_DAYS)->toDateString(),
            ], $user);
            // On the privacy request list (screen 71), on hold for the 30 days.
            $this->privacy->deletionRequested($user);
        });

        if ($user->email !== null) {
            $user->notify(new AccountDeletionScheduled(now()->addDays(self::GRACE_DAYS)));
        }
    }

    public function cancel(User $user): void
    {
        $user->forceFill(['deletion_requested_at' => null])->save();
        $this->privacy->deletionWithdrawn($user);

        $this->audit->record('account.deletion_cancelled', 'user', (string) $user->id, [], $user);
    }

    /**
     * Deletes accounts whose grace period has ended.
     *
     * @return int how many were deleted
     */
    public function purge(): int
    {
        $count = 0;

        User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays(self::GRACE_DAYS))
            ->lazyById()
            ->each(function (User $user) use (&$count): void {
                $id = (string) $user->id;
                $this->privacy->deletionDone($user);
                // The inbox is polymorphic, so it has no cascade (B6.2).
                $user->notifications()->delete();
                $user->delete();
                // The audit row keeps only the id: the person's details are gone.
                $this->audit->record('account.deleted', 'user', $id);
                $count++;
            });

        return $count;
    }
}
