<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The same contact in one source (B7.1): a CRM's id for them, or the phone's.
 *
 * @property int $id
 * @property int $contact_id
 * @property string $owner_key
 * @property string $source
 * @property int|null $connection_id
 * @property string $external_id
 * @property Carbon|null $remote_updated_at
 * @property Carbon|null $synced_at
 * @property string|null $echo_hash
 * @property-read Contact $contact
 */
final class ContactLink extends Model
{
    protected $fillable = ['contact_id', 'owner_key', 'source', 'connection_id', 'external_id', 'remote_updated_at', 'synced_at', 'echo_hash'];

    protected function casts(): array
    {
        return ['remote_updated_at' => 'datetime', 'synced_at' => 'datetime'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }
}
