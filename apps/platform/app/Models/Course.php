<?php

declare(strict_types=1);

namespace App\Models;

use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Models\Concerns\StateTagged;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A course (A5.3, ADR 0020): modules of lessons, tagged by state. CE and
 * qualifying courses record the regulator's approval number per state and
 * can't go live in a state without one (screen 63: "no labels without
 * approval").
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property CourseType $type
 * @property string|null $description
 * @property list<string>|null $states Null: every state
 * @property string|null $hours
 * @property array<string, array{number: string, expires_on: string|null}>|null $approvals
 * @property CourseStatus $status
 * @property string|null $status_note
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, CourseModule> $modules
 * @property-read Collection<int, Lesson> $lessons
 */
final class Course extends Model
{
    use SoftDeletes;
    use StateTagged;

    protected $fillable = ['title', 'slug', 'type', 'description', 'states', 'hours', 'approvals', 'status', 'status_note', 'created_by', 'published_at'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'type' => CourseType::class,
            'status' => CourseStatus::class,
            'states' => 'array',
            'approvals' => 'array',
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

    /**
     * States this course is tagged to that have no approval number yet (CE and qualifying only).
     *
     * @return list<string>
     */
    public function missingApprovals(): array
    {
        if (! $this->type->needsApproval()) {
            return [];
        }

        $approvals = $this->approvals ?? [];

        return array_values(array_filter($this->states ?? [], static fn (string $code): bool => trim($approvals[$code]['number'] ?? '') === ''));
    }
}
