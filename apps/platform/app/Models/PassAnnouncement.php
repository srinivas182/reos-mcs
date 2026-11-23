<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Jordan from Katy passed the Texas exam!" (A10.3b, f05 and f06): who
 * hears is the candidate's choice; never scores.
 *
 * @property int $id
 * @property int $user_id
 * @property string $state_code
 * @property string $first_name
 * @property string|null $city
 * @property string|null $tip
 * @property bool $to_group
 * @property bool $to_school
 * @property bool $to_state
 * @property int $congrats_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class PassAnnouncement extends Model
{
    protected $fillable = ['user_id', 'state_code', 'first_name', 'city', 'tip', 'to_group', 'to_school', 'to_state', 'congrats_count'];

    protected function casts(): array
    {
        return ['to_group' => 'boolean', 'to_school' => 'boolean', 'to_state' => 'boolean', 'congrats_count' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
