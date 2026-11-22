<?php

declare(strict_types=1);

namespace App\Mail\Ses;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Addresses REOS no longer emails (B6.2d): a permanent bounce or a spam
 * complaint from Amazon SES. The person sees why in Settings ›
 * Notifications and can resume once they've fixed it.
 */
final readonly class EmailSuppressions
{
    public const BOUNCE = 'bounce';

    public const COMPLAINT = 'complaint';

    public function __construct(private AuditLogger $audit) {}

    public function suppress(string $email, string $reason, ?string $detail): void
    {
        $email = Str::lower(trim($email));

        if ($email === '' || $this->suppressed($email)) {
            return;
        }

        DB::table('email_suppressions')->insertOrIgnore([
            'email' => $email,
            'reason' => $reason,
            'detail' => $detail === null ? null : Str::limit($detail, 300, ''),
            'created_at' => now(),
        ]);

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        // An address with no account is named by its domain only.
        $user === null
            ? $this->audit->record('email.suppressed', 'email', '•••@'.Str::after($email, '@'), ['reason' => $reason])
            : $this->audit->record('email.suppressed', 'user', (string) $user->id, ['reason' => $reason]);
    }

    public function suppressed(string $email): bool
    {
        return DB::table('email_suppressions')->where('email', Str::lower(trim($email)))->exists();
    }

    /** @return array{reason: string, at: string}|null */
    public function for(User $user): ?array
    {
        if ($user->email === null) {
            return null;
        }

        $row = DB::table('email_suppressions')->where('email', Str::lower($user->email))->first(['reason', 'created_at']);

        if ($row === null || ! is_string($row->reason) || ! is_string($row->created_at)) {
            return null;
        }

        return ['reason' => $row->reason, 'at' => CarbonImmutable::parse($row->created_at)->toIso8601String()];
    }

    /** The person fixed their mailbox (or changed their mind about the complaint). */
    public function resume(User $user): void
    {
        if ($user->email === null) {
            return;
        }

        if (DB::table('email_suppressions')->where('email', Str::lower($user->email))->delete() > 0) {
            $this->audit->record('email.resumed', 'user', (string) $user->id, [], $user);
        }
    }
}
