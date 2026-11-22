<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person using a sponsor's code (B5.1b), so the sponsor sees who used it.
 *
 * @property int $id
 * @property int $sponsor_code_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read SponsorCode $code
 */
final class SponsorCodeRedemption extends Model
{
    protected $fillable = ['sponsor_code_id', 'user_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SponsorCode, $this> */
    public function code(): BelongsTo
    {
        return $this->belongsTo(SponsorCode::class, 'sponsor_code_id');
    }
}
