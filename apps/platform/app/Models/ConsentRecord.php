<?php

declare(strict_types=1);

namespace App\Models;

use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One consent given or withdrawn (A6.3). Never edited or deleted; the
 * latest record for a person, purpose and subject is what counts.
 *
 * @property int $id
 * @property int|null $user_id
 * @property ConsentPurpose $purpose
 * @property string|null $subject
 * @property bool $granted
 * @property string $version
 * @property ConsentMethod $method
 * @property int|null $actor_id
 * @property string|null $note
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
final class ConsentRecord extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'purpose', 'subject', 'granted', 'version', 'method', 'actor_id', 'note', 'ip_address', 'user_agent'];

    protected static function booted(): void
    {
        self::updating(static function (self $record): void {
            // Account deletion unlinks the person (the foreign key sets null); nothing else changes.
            if (array_keys($record->getDirty()) !== ['user_id'] || $record->user_id !== null) {
                throw new LogicException('Consent records are never edited; record a new one.');
            }
        });
        self::deleting(static fn (): never => throw new LogicException('Consent records are never deleted.'));
    }

    protected function casts(): array
    {
        return ['purpose' => ConsentPurpose::class, 'method' => ConsentMethod::class, 'granted' => 'boolean', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
