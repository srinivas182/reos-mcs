<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A private study group (A9.3c, screen 52): up to 20 candidates, joined by
 * invite link; a school's class group has its organisation.
 *
 * @property int $id
 * @property string $uuid The invite link's id
 * @property string $name
 * @property int|null $owner_id
 * @property int|null $organisation_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $members
 */
final class StudyGroup extends Model
{
    /** @var list<string> */
    protected $fillable = ['uuid', 'name', 'owner_id', 'organisation_id'];

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'study_group_members')->withPivot('joined_at');
    }
}
