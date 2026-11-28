<?php

declare(strict_types=1);

namespace App\Leads;

use App\Billing\Ledger\Points;
use App\Enums\LicenceStatus;
use App\Models\Licence;
use App\Models\User;
use App\Notifications\InviteRewarded;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invite and earn (B14.3b, ADR 0067): each person has their own link. When
 * someone who joined through it gets a verified license, whoever invited
 * them earns points (REOS's points: no cash value, spent inside REOS on
 * mentor sessions, AI credits or membership). Once per person invited, at
 * most MAX_PER_YEAR a year; never for inviting yourself.
 *
 * Joining through the link on the web remembers who invited them; someone
 * who signed up in the app can enter the code within CLAIM_DAYS.
 *
 * @phpstan-type InvitedRow array{name: string, joined_at: string, licensed: bool}
 * @phpstan-type InviteSummary array{code: string, link: string, points_each: int, earned: int, invited: list<InvitedRow>, invited_by: string|null, can_claim: bool}
 */
final readonly class Invites
{
    /** 2,500 points ($25 inside REOS) when the person invited is licensed. */
    public const POINTS = 2500;

    public const MAX_PER_YEAR = 25;

    public const CLAIM_DAYS = 30;

    /** Codes use letters and digits that can't be mistaken for each other. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(private Points $points, private AuditLogger $audit) {}

    /** @return InviteSummary */
    public function summary(User $user): array
    {
        $code = $this->code($user);
        $invited = User::query()->where('invited_by_id', $user->id)->latest('invited_at')->limit(100)->get();
        $licensed = Licence::query()->whereIn('user_id', $invited->pluck('id'))->where('status', LicenceStatus::Active)->whereNotNull('verified_at')->distinct()->pluck('user_id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();

        return [
            'code' => $code, 'link' => route('join.show', $code), 'points_each' => self::POINTS,
            'earned' => self::POINTS * count($licensed),
            'invited' => array_values($invited->map(static fn (User $u): array => [
                'name' => self::shortName((string) $u->name), 'joined_at' => ($u->invited_at ?? $u->created_at ?? CarbonImmutable::now())->toIso8601String(), 'licensed' => in_array($u->id, $licensed, true),
            ])->all()),
            'invited_by' => $user->invited_by_id === null ? null : self::shortName((string) User::query()->find($user->invited_by_id)?->name),
            'can_claim' => $this->canClaim($user),
        ];
    }

    /** The person behind a link, for the join page. */
    public function inviter(string $code): ?User
    {
        return User::query()->where('invite_code', strtoupper(trim($code)))->first();
    }

    /**
     * Remembers who invited a new person (from the join link on the web, or
     * the code typed in the app). Returns false when it doesn't apply.
     */
    public function claim(User $user, ?string $code, bool $typed = false): bool
    {
        $inviter = $code === null ? null : $this->inviter($code);

        if ($inviter === null || $inviter->id === $user->id || ! $this->canClaim($user)) {
            if ($typed) {
                throw ValidationException::withMessages(['code' => __('leads.invites.cantClaim')]);
            }

            return false;
        }

        $user->forceFill(['invited_by_id' => $inviter->id, 'invited_at' => CarbonImmutable::now()])->save();
        $this->audit->record('invite.claimed', 'user', (string) $user->id, ['inviter' => $inviter->id], $user);

        return true;
    }

    /**
     * A verified license (from the TREC lookup or a reviewer): the person
     * who invited them earns their points, once.
     */
    public function licensed(User $user): void
    {
        if ($user->invited_by_id === null) {
            return;
        }

        $inviter = User::query()->find($user->invited_by_id);

        if ($inviter === null || $this->rewardedThisYear($inviter) > self::MAX_PER_YEAR) {
            return;
        }

        if ($this->points->award($inviter, self::POINTS, "invite:{$user->id}:licensed")) {
            $inviter->notify(new InviteRewarded(self::shortName((string) $user->name), self::POINTS));
        }
    }

    /** New enough, no-one recorded yet, and not licensed yet (a license already held isn't "becoming licensed"). */
    private function canClaim(User $user): bool
    {
        return $user->invited_by_id === null
            && ($user->created_at === null || $user->created_at->greaterThan(CarbonImmutable::now()->subDays(self::CLAIM_DAYS)))
            && ! Licence::query()->where('user_id', $user->id)->where('status', LicenceStatus::Active)->exists();
    }

    /** Licensed in the last year, counting the one just licensed. */
    private function rewardedThisYear(User $inviter): int
    {
        $invited = User::query()->where('invited_by_id', $inviter->id)->pluck('id');

        return Licence::query()->whereIn('user_id', $invited)->where('status', LicenceStatus::Active)->where('verified_at', '>=', CarbonImmutable::now()->subYear())->distinct()->count('user_id');
    }

    private function code(User $user): string
    {
        if ($user->invite_code !== null) {
            return $user->invite_code;
        }

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (User::query()->where('invite_code', $code)->exists());

        $user->forceFill(['invite_code' => $code])->save();

        return $code;
    }

    /** "Ana R.": other people's names are shown short. */
    private static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? (string) end($parts) : '';

        return $last === '' ? $first : $first.' '.Str::upper(mb_substr($last, 0, 1)).'.';
    }
}
