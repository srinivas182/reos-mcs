<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person's account at a tenant's identity provider, linked to their REOS
 * account on their first single sign-on (A4.2c).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $user_id
 * @property string $subject
 * @property string|null $email
 * @property Carbon|null $last_signed_in_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['organisation_id', 'user_id', 'subject', 'email', 'last_signed_in_at'])]
final class SsoIdentity extends Model
{
    use BelongsToTenant;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_signed_in_at' => 'datetime'];
    }
}
