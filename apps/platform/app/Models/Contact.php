<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\HasPersonalRows;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A person in an agent's world (B7.1, ADR 0027), once, however many sources
 * know them. Belongs to the agent (personal tools) or the brokerage (its CRM).
 *
 * @property int $id
 * @property string $uuid
 * @property string $owner_type user or organisation
 * @property int $owner_id
 * @property-read int|null $organisation_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property list<array{value: string, source: string}> $emails
 * @property list<array{value: string, source: string}> $phones
 * @property array<string, string>|null $address
 * @property Carbon|null $birthday
 * @property string|null $stage
 * @property array<string, string> $field_times
 * @property int|null $merged_into_id
 * @property array{links: list<int>, emails: list<string>, phones: list<string>, tags: list<int>}|null $merge_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ContactLink> $links
 * @property-read Collection<int, Tag> $tags
 */
final class Contact extends Model implements HasPersonalRows
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = ['uuid', 'owner_type', 'owner_id', 'first_name', 'last_name', 'emails', 'phones', 'address', 'birthday', 'stage', 'field_times', 'merged_into_id', 'merge_snapshot'];

    protected $attributes = ['emails' => '[]', 'phones' => '[]', 'field_times' => '{}'];

    protected function casts(): array
    {
        return ['emails' => 'array', 'phones' => 'array', 'address' => 'array', 'birthday' => 'date', 'field_times' => 'array', 'merge_snapshot' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function name(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : ($this->emails[0]['value'] ?? $this->phones[0]['value'] ?? '');
    }

    /** @return HasMany<ContactLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(ContactLink::class);
    }

    /** @return HasMany<ContactActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ContactActivity::class);
    }

    /** @return HasMany<ContactNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tag');
    }
}
