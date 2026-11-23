<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\HubEncrypted;
use App\Connections\ConnectionStatus;
use App\Connections\HistoryRange;
use App\Integrations\IntegrationMode;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\HasPersonalRows;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person's or an organisation's own account at an outside tool (B3.1,
 * ADR 0013), for example Maria's Google Calendar.
 *
 * @property int $id
 * @property string $integration_id
 * @property string $owner_type
 * @property int $owner_id
 * @property-read int|null $organisation_id The owning organisation (generated from the owner), or null for a person's own
 * @property int|null $connected_by
 * @property IntegrationMode $mode
 * @property string $external_account_id
 * @property string|null $account_label
 * @property list<string> $scopes
 * @property ConnectionStatus $status
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property array{history?: string, calendars?: array<string, string>, write_calendar?: string|null, default_for_new?: bool} $settings
 * @property array<string, string> $cursors
 * @property array<string, mixed> $meta
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $last_failed_at
 * @property string|null $last_error
 * @property int $consecutive_failures
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Connection extends Model implements HasPersonalRows
{
    use BelongsToTenant;

    public const OWNER_USER = 'user';

    public const OWNER_ORGANISATION = 'organisation';

    protected $fillable = [
        'integration_id', 'owner_type', 'owner_id', 'connected_by', 'mode', 'external_account_id', 'account_label',
        'scopes', 'status', 'access_token', 'refresh_token', 'token_expires_at', 'settings', 'cursors', 'meta',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'scopes' => '[]',
        'settings' => '{}',
        'cursors' => '{}',
        'meta' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'mode' => IntegrationMode::class,
            'status' => ConnectionStatus::class,
            'scopes' => 'array',
            'settings' => 'array',
            'cursors' => 'array',
            'meta' => 'array',
            'access_token' => HubEncrypted::class,
            'refresh_token' => HubEncrypted::class,
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    /**
     * The person or organisation whose account this is. Not a morph relation:
     * a morph map would change how existing role rows name the user model.
     */
    public function owner(): User|Organisation|null
    {
        return match ($this->owner_type) {
            self::OWNER_USER => User::query()->find($this->owner_id),
            self::OWNER_ORGANISATION => Organisation::query()->find($this->owner_id),
            default => null,
        };
    }

    /** People to tell about problems: the owner, or the person who connected an organisation's account. */
    public function contact(): ?User
    {
        $owner = $this->owner();

        return $owner instanceof User ? $owner : $this->connectedBy()->first();
    }

    /** @return BelongsTo<User, $this> */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function history(): HistoryRange
    {
        return HistoryRange::tryFrom($this->settings['history'] ?? '') ?? HistoryRange::DEFAULT;
    }

    public function cursor(string $stream): ?string
    {
        return $this->cursors[$stream] ?? null;
    }

    /** "user:12" or "organisation:3": who the fairness limits are counted against. */
    public function ownerKey(): string
    {
        return "{$this->owner_type}:{$this->owner_id}";
    }
}
