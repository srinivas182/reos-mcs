<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A contact's AI summary and next step (B8.3), kept until the contact
 * changes.
 *
 * @property int $id
 * @property int $contact_id
 * @property string $summary
 * @property string $next_step
 * @property string $why
 * @property string $channel
 * @property string|null $draft
 * @property string $language
 * @property string $fingerprint
 * @property string $engine
 * @property Carbon $generated_at
 */
final class ContactInsight extends Model
{
    public const CHANNELS = ['call', 'text', 'email', 'note'];

    protected $fillable = ['contact_id', 'summary', 'next_step', 'why', 'channel', 'draft', 'language', 'fingerprint', 'engine', 'generated_at'];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
