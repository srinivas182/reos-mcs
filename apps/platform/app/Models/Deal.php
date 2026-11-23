<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A deal (B9.1, ADR 0037): from a transaction tool (read-only in REOS,
 * decision 3) or tracked in REOS by the agent.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $organisation_id
 * @property int|null $connection_id
 * @property string $source `reos` or the tool's register id
 * @property string|null $external_id
 * @property string|null $external_url Opens the deal in its tool
 * @property string $property
 * @property string|null $city
 * @property string $side buy, sell, lease or both
 * @property string $stage active, under_contract, closed or cancelled
 * @property int|null $price Whole dollars
 * @property string|null $commission
 * @property list<array{name: string, role: string, email: string|null, phone: string|null}> $parties
 * @property list<array{name: string, status: string, url: string|null}> $documents status: complete, missing or pending
 * @property Carbon|null $closed_at
 * @property Carbon|null $source_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, DealDate> $dates
 * @property-read Collection<int, DealFile> $files
 * @property-read Collection<int, SignatureRequest> $signatureRequests
 */
final class Deal extends Model
{
    use SoftDeletes;

    public const SIDES = ['buy', 'sell', 'lease', 'both'];

    public const STAGES = ['active', 'under_contract', 'closed', 'cancelled'];

    /** @var list<string> */
    protected $fillable = ['uuid', 'user_id', 'organisation_id', 'connection_id', 'source', 'external_id', 'external_url', 'property', 'city', 'side', 'stage', 'price', 'commission', 'parties', 'documents', 'closed_at', 'source_updated_at'];

    /** Deals from a tool are changed in the tool (decision 3). */
    public function readOnly(): bool
    {
        return $this->source !== 'reos';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<DealDate, $this> */
    public function dates(): HasMany
    {
        return $this->hasMany(DealDate::class)->orderBy('due_on')->orderBy('id');
    }

    /** @return HasMany<DealFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(DealFile::class)->orderByDesc('id');
    }

    /** @return HasMany<SignatureRequest, $this> */
    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class)->orderByDesc('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['parties' => 'array', 'documents' => 'array', 'price' => 'integer', 'commission' => 'decimal:2', 'closed_at' => 'datetime', 'source_updated_at' => 'datetime'];
    }
}
