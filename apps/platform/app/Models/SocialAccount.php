<?php

declare(strict_types=1);

namespace App\Models;

use App\Identity\IdentityProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Google or Apple identity linked to a REOS account.
 *
 * @property int $id
 * @property int $user_id
 * @property IdentityProvider $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'email'])]
final class SocialAccount extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['provider' => IdentityProvider::class];
    }
}
