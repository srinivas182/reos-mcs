<?php

declare(strict_types=1);

namespace App\Models;

use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Content\Delivery;
use App\Models\Concerns\StateTagged;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A course (A5.3, ADR 0020): modules of lessons, tagged by state. CE and
 * qualifying courses need the regulator's approval in each state, for the
 * way they're delivered (course approvals, A7.1, ADR 0025), to go live and
 * to carry their label (screens 50 and 63: "no labels without approval").
 * Owned by a school (A7.2) or by REOS (no organisation).
 *
 * @property int $id
 * @property int|null $organisation_id Owner school; null for REOS's own courses
 * @property string $title
 * @property string $slug
 * @property CourseType $type
 * @property Delivery $delivery
 * @property string|null $description
 * @property list<string>|null $states Null: every state
 * @property string|null $hours
 * @property list<array{key: string, by: int, at: string}>|null $cleared_flags Legal flags a reviewer checked
 * @property CourseStatus $status
 * @property string|null $status_note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, CourseModule> $modules
 * @property-read Collection<int, Lesson> $lessons
 * @property-read Collection<int, CourseApproval> $approvals
 * @property-read Organisation|null $owner
 */
final class Course extends Model
{
    use SoftDeletes;
    use StateTagged;

    protected $fillable = ['organisation_id', 'title', 'slug', 'type', 'delivery', 'description', 'states', 'hours', 'cleared_flags', 'status', 'status_note', 'reviewed_by', 'reviewed_at', 'created_by', 'published_at'];

    protected $attributes = ['status' => 'draft', 'delivery' => 'self_paced'];

    protected function casts(): array
    {
        return [
            'type' => CourseType::class,
            'status' => CourseStatus::class,
            'states' => 'array',
            'delivery' => Delivery::class,
            'cleared_flags' => 'array',
            'reviewed_at' => 'datetime',
            'hours' => 'decimal:1',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<CourseModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<CourseApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(CourseApproval::class)->orderBy('state_code')->orderBy('delivery');
    }

    /** @return BelongsTo<Organisation, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /** The approval that gives the course its label in a state, for its delivery, on the day (A7.1). */
    public function approvalIn(string $state, ?CarbonImmutable $on = null): ?CourseApproval
    {
        return $this->approvals->first(fn (CourseApproval $a): bool => $a->state_code === strtoupper($state) && $a->delivery === $this->delivery && $a->valid($on));
    }

    /**
     * Approval numbers per state for the course's delivery (the content
     * library's quick edit, A5.3), approved ones only.
     *
     * @return array<string, array{number: string, expires_on: string|null}>
     */
    public function approvalNumbers(): array
    {
        $numbers = [];

        foreach ($this->approvals as $approval) {
            if ($approval->delivery === $this->delivery && $approval->status === CourseApproval::APPROVED && trim((string) $approval->number) !== '') {
                $numbers[$approval->state_code] = ['number' => (string) $approval->number, 'expires_on' => $approval->expires_on?->toDateString()];
            }
        }

        ksort($numbers);

        return $numbers;
    }

    /** Whether the course may be called CE or qualifying in the state (always true for other types). */
    public function creditableIn(string $state, ?CarbonImmutable $on = null): bool
    {
        return ! $this->type->needsApproval() || $this->approvalIn($state, $on) !== null;
    }

    /**
     * States this course is tagged to without an approval in force for its
     * delivery (CE and qualifying only).
     *
     * @return list<string>
     */
    public function missingApprovals(?CarbonImmutable $on = null): array
    {
        if (! $this->type->needsApproval()) {
            return [];
        }

        return array_values(array_filter($this->states ?? [], fn (string $code): bool => $this->approvalIn($code, $on) === null));
    }
}
