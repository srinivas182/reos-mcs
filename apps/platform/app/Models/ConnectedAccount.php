<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Stripe Connect account that receives payouts (B5.2b): a school's
 * (owner_type organisation) or an instructor's or mentor's (owner_type
 * user). Express accounts are onboarded through REOS; a tenant may connect
 * its own Stripe account instead (standard). Bank and tax details stay
 * with Stripe.
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $environment
 * @property string $kind
 * @property string $stripe_account_id
 * @property bool $details_submitted
 * @property bool $payouts_enabled
 * @property list<string>|null $requirements What Stripe still needs (field names)
 * @property Carbon|null $checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ConnectedAccount extends Model
{
    public const ORGANISATION = 'organisation';

    public const USER = 'user';

    public const EXPRESS = 'express';

    public const STANDARD = 'standard';

    protected $fillable = ['owner_type', 'owner_id', 'environment', 'kind', 'stripe_account_id', 'details_submitted', 'payouts_enabled', 'requirements', 'checked_at'];

    protected function casts(): array
    {
        return [
            'details_submitted' => 'boolean',
            'payouts_enabled' => 'boolean',
            'requirements' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    /** Ready, still being set up, or waiting for something Stripe needs. */
    public function state(): string
    {
        return match (true) {
            $this->payouts_enabled => 'ready',
            ! $this->details_submitted => 'onboarding',
            default => 'needsInfo',
        };
    }
}
