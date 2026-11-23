<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something that happened to a contact (B7.1): created or linked from a
 * source, a field changed (with the value it replaced), a change kept back
 * because REOS's was newer, removed at a source, merged or split; and later
 * calls, emails, showings and stage changes. Shown as Sync activity (screen 23).
 *
 * @property int $id
 * @property int $contact_id
 * @property string $kind
 * @property string $source
 * @property array<string, string|null>|null $details
 * @property Carbon $occurred_at
 * @property-read Contact $contact
 */
final class ContactActivity extends Model
{
    protected $fillable = ['contact_id', 'kind', 'source', 'details', 'occurred_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }
}
