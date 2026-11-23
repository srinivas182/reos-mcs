<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Ledger\SponsorPools;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\SponsorCode;
use App\Models\SponsorCodeRedemption;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sponsor codes (B5.1b; candidate screen 6 and addition 6): a brokerage,
 * school or association pays for seats and shares a code; each person who
 * enters it gets the product's access, once. B5.3 funds them from sponsor
 * pools; until then REOS issues them on the tenant's Plan tab.
 */
final readonly class SponsorCodes
{
    public const AUDIT_SUBJECT = 'sponsor_code';

    /** Letters and digits that can't be confused (no 0/O, 1/I). */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private Entitlements $entitlements, private AuditLogger $audit, private SponsorPools $pools) {}

    public function create(Organisation $sponsor, Product $product, string $state, int $seats, ?int $accessMonths, ?CarbonInterface $expiresAt, User $actor): SponsorCode
    {
        $prefix = Str::upper(Str::substr((string) preg_replace('/[^A-Za-z]/', '', $sponsor->name), 0, 3)) ?: 'REOS';

        do {
            $code = $prefix.'-'.collect(range(1, 6))->map(static fn (): string => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');
        } while (SponsorCode::query()->where('code', $code)->exists());

        return DB::transaction(function () use ($sponsor, $product, $state, $seats, $accessMonths, $expiresAt, $actor, $code): SponsorCode {
            // Seats come from the sponsor's pool, when it has one (B5.3).
            $this->pools->drawSeats($sponsor, $seats, $code);

            $sponsorCode = SponsorCode::query()->create([
                'code' => $code,
                'sponsor_organisation_id' => $sponsor->id,
                'product_id' => $product->id,
                'state_code' => $state,
                'seats' => $seats,
                'access_months' => $accessMonths,
                'expires_at' => $expiresAt,
                'created_by' => $actor->id,
            ]);

            $this->audit->record('code.created', self::AUDIT_SUBJECT, $code, ['product' => $product->key, 'seats' => $seats], $actor, $sponsor->id);

            return $sponsorCode;
        });
    }

    public function deactivate(SponsorCode $code, User $actor): void
    {
        $code->forceFill(['active' => false])->save();
        $this->pools->returnSeats($code);
        $this->audit->record('code.deactivated', self::AUDIT_SUBJECT, $code->code, ['from' => 'active', 'to' => 'stopped', 'used' => $code->used], $actor, $code->sponsor_organisation_id);
    }

    /** "Enter a code": the person gets the product's access, once per code, while seats last. */
    /**
     * What a code covers, before signing in to use it ("Have a code?" on
     * reos.com, A8.3b; screen 11's facts). Nothing is used up.
     *
     * @return array{code: string, sponsor: string, product: string, state: string, months: int|null, expires_at: string|null}
     *
     * @throws ValidationException when the code is unknown, expired or full, saying which
     */
    public function preview(string $input): array
    {
        $value = Str::upper((string) preg_replace('/\s+/', '', $input));
        $code = SponsorCode::query()->with(['product', 'sponsor'])->where('code', $value)->first();
        $error = match (true) {
            $code === null || ! $code->active => 'billing.codes.errors.unknown',
            $code->expires_at !== null && $code->expires_at->isPast() => 'billing.codes.errors.expired',
            $code->used >= $code->seats => 'billing.codes.errors.full',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['code' => __($error)]);
        }

        return [
            'code' => $code->code, 'sponsor' => $code->sponsor->name, 'product' => $code->product->name, 'state' => $code->state_code,
            'months' => $code->access_months, 'expires_at' => $code->expires_at?->toIso8601String(),
        ];
    }

    public function redeem(User $user, string $input): SponsorCode
    {
        $value = Str::upper((string) preg_replace('/\s+/', '', $input));

        return DB::transaction(function () use ($user, $value): SponsorCode {
            $code = SponsorCode::query()->with(['product', 'sponsor'])->where('code', $value)->lockForUpdate()->first();

            $error = match (true) {
                $code === null || ! $code->active => 'billing.codes.errors.unknown',
                $code->expires_at !== null && $code->expires_at->isPast() => 'billing.codes.errors.expired',
                SponsorCodeRedemption::query()->where('sponsor_code_id', $code->id)->where('user_id', $user->id)->exists() => 'billing.codes.errors.used',
                $code->used >= $code->seats => 'billing.codes.errors.full',
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages(['code' => __($error)]);
            }

            $redemption = SponsorCodeRedemption::query()->create(['sponsor_code_id' => $code->id, 'user_id' => $user->id]);
            $code->increment('used');

            $this->entitlements->grant(
                $user,
                $code->product->features ?? [],
                EntitlementSource::Sponsor,
                $redemption->id,
                $code->state_code,
                $code->access_months === null ? null : now()->addMonths($code->access_months),
            );

            $this->audit->record('code.redeemed', self::AUDIT_SUBJECT, $code->code, ['product' => $code->product->key], $user, $code->sponsor_organisation_id);

            return $code;
        });
    }
}
