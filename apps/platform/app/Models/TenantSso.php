<?php

declare(strict_types=1);

namespace App\Models;

use App\Sso\SsoProtocol;
use App\Sso\SsoStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A tenant's single sign-on (A4.2c, screen 57).
 *
 * @property int $id
 * @property int $organisation_id
 * @property SsoProtocol $protocol
 * @property SsoStatus $status
 * @property array<string, mixed> $settings
 * @property string|null $client_secret
 * @property Carbon|null $last_test_at
 * @property bool|null $last_test_passed
 * @property string|null $last_test_detail
 * @property Carbon|null $enabled_at
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $organisation
 */
#[Fillable(['organisation_id', 'protocol', 'status', 'settings', 'client_secret', 'last_test_at', 'last_test_passed', 'last_test_detail', 'enabled_at', 'updated_by'])]
#[Hidden(['client_secret'])]
final class TenantSso extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_sso';

    /** A setting as text ('' when missing). */
    public function setting(string $key): string
    {
        $value = $this->settings[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /** @return list<string> */
    public function certificates(): array
    {
        $value = $this->settings['certificates'] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'protocol' => SsoProtocol::class,
            'status' => SsoStatus::class,
            'settings' => 'array',
            'client_secret' => 'encrypted',
            'last_test_at' => 'datetime',
            'last_test_passed' => 'boolean',
            'enabled_at' => 'datetime',
        ];
    }
}
