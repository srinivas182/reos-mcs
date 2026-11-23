<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An instructor's consent to an AI avatar of them, for one school (A7.1b,
 * ADR 0025): given with a signed template version and a 2-minute sample,
 * used only by that school and only for lessons they approve. Withdrawn,
 * the sample is deleted and lessons using the avatar must be unpublished or
 * re-rendered within 30 days.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $organisation_id
 * @property string $status
 * @property string $version
 * @property int|null $consent_record_id
 * @property string|null $sample_path
 * @property string|null $sample_mime
 * @property int|null $sample_size
 * @property array<string, string>|null $vendor_avatars
 * @property Carbon|null $given_at
 * @property Carbon|null $withdrawn_at
 * @property CarbonImmutable|null $lessons_due_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Organisation $organisation
 */
final class AvatarConsent extends Model
{
    use BelongsToTenant;

    public const GIVEN = 'given';

    public const WITHDRAWN = 'withdrawn';

    public const ENDED = 'ended';

    /** Days a school has to stop using an avatar once consent ends. */
    public const WIND_DOWN_DAYS = 30;

    protected $fillable = ['uuid', 'user_id', 'organisation_id', 'status', 'version', 'consent_record_id', 'sample_path', 'sample_mime', 'sample_size', 'vendor_avatars', 'given_at', 'withdrawn_at', 'lessons_due_by'];

    protected function casts(): array
    {
        return [
            'vendor_avatars' => 'array',
            'sample_size' => 'integer',
            'given_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'lessons_due_by' => 'immutable_date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** The consent record subject: one school. */
    public static function subject(Organisation $school): string
    {
        return 'school:'.$school->id;
    }
}
