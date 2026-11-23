<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A note on a contact (B7.1), written in REOS or in a CRM (two-way from B7.2).
 *
 * @property int $id
 * @property int $contact_id
 * @property string $body
 * @property string $source
 * @property string|null $external_id
 * @property array<string, string>|null $pushed Where REOS sent it, and its id there (B7.2)
 * @property int|null $author_id
 * @property Carbon|null $remote_updated_at
 * @property Carbon|null $created_at
 * @property-read Contact $contact
 */
final class ContactNote extends Model
{
    use SoftDeletes;

    protected $fillable = ['contact_id', 'body', 'source', 'external_id', 'pushed', 'author_id', 'remote_updated_at'];

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    protected function casts(): array
    {
        return ['remote_updated_at' => 'datetime', 'pushed' => 'array'];
    }
}
