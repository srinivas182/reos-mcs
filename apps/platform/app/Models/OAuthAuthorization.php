<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\HubEncrypted;
use App\Integrations\IntegrationMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One vendor sign-in in progress: the PKCE verifier and who asked. Single use,
 * valid for ten minutes, so a `state` can't be replayed.
 *
 * @property string $id
 * @property string $integration_id
 * @property int $user_id
 * @property string $owner_type
 * @property int $owner_id
 * @property IntegrationMode $mode
 * @property string $code_verifier
 * @property string $return_to
 * @property array{history?: string} $settings
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property-read User $user
 */
final class OAuthAuthorization extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const RETURN_WEB = 'web';

    public const RETURN_APP = 'app';

    protected $table = 'oauth_authorizations';

    protected $fillable = ['integration_id', 'user_id', 'owner_type', 'owner_id', 'mode', 'code_verifier', 'return_to', 'settings', 'expires_at'];

    protected $hidden = ['code_verifier'];

    protected function casts(): array
    {
        return [
            'mode' => IntegrationMode::class,
            'code_verifier' => HubEncrypted::class,
            'settings' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
