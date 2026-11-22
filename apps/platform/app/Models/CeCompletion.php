<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * CE a person completed (A5.2): a REOS course, another provider's, or a
 * commission meeting. The CE card counts it against the requirement.
 *
 * @property int $id
 * @property int $user_id
 * @property string $state_code
 * @property string $title
 * @property string|null $course_code The mandatory course it counts as, or null for an elective
 * @property string $hours Decimal, one place
 * @property string|null $provider
 * @property string $source `reos`, `other_provider` or `commission_meeting`
 * @property Carbon $completed_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CeCompletion extends Model
{
    use SoftDeletes;

    public const SOURCES = ['reos', 'other_provider', 'commission_meeting'];

    protected $fillable = ['user_id', 'state_code', 'title', 'course_code', 'hours', 'provider', 'source', 'completed_on'];

    protected function casts(): array
    {
        return ['completed_on' => 'date', 'hours' => 'decimal:1'];
    }
}
