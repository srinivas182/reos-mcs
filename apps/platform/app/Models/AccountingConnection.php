<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\HubEncrypted;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A brokerage's QuickBooks Online company (B17.3): tokens encrypted, the accounts mapped once.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $integration_id
 * @property string $mode simulator, sandbox or live
 * @property string $realm_id
 * @property string $company_name
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property array<string, string>|null $accounts
 * @property int|null $connected_by
 * @property string $status connected or needs_reconnect
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class AccountingConnection extends Model
{
    protected $fillable = ['organisation_id', 'integration_id', 'mode', 'realm_id', 'company_name', 'access_token', 'refresh_token', 'token_expires_at', 'accounts', 'connected_by', 'status', 'last_error'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => HubEncrypted::class, 'refresh_token' => HubEncrypted::class, 'token_expires_at' => 'datetime', 'accounts' => 'array'];
    }
}
