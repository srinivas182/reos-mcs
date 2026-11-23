<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A candidate's licence journey (A10.3a, ADR 0041): their check-offs of the
 * state's steps, the application date, the booked exam, the exam-day
 * checklist and every exam attempt per portion.
 *
 * @property int $id
 * @property int $user_id
 * @property string $state_code
 * @property array<string, string> $steps code => date checked off (Y-m-d)
 * @property CarbonImmutable|null $application_filed_on
 * @property CarbonImmutable|null $exam_on
 * @property string|null $exam_time HH:MM, local to the test centre
 * @property string|null $test_centre
 * @property list<string> $kit
 * @property list<array{portion: string, passed: bool, on: string}> $attempts
 * @property list<string> $weak_topics
 * @property list<string> $reminded
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class LicenceJourney extends Model
{
    protected $fillable = ['user_id', 'state_code', 'steps', 'application_filed_on', 'exam_on', 'exam_time', 'test_centre', 'kit', 'attempts', 'weak_topics', 'reminded'];

    protected function casts(): array
    {
        return [
            'steps' => 'array', 'kit' => 'array', 'attempts' => 'array', 'weak_topics' => 'array', 'reminded' => 'array',
            'application_filed_on' => 'immutable_date', 'exam_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
