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
 * @property CarbonImmutable|null $passed_on The day both portions were passed (A10.3b)
 * @property string|null $news shared or skipped, after passing (A10.3b)
 * @property bool $offers_opt_in Brokerages in the state may make offers (A10.3b)
 * @property string|null $offers_city Where the candidate wants to work
 * @property CarbonImmutable|null $offers_opted_at
 * @property string|null $licence_number Entered to switch to agent (A10.3c)
 * @property string|null $licence_check verified, inactive, name_mismatch, not_found or no_lookup
 * @property CarbonImmutable|null $licence_checked_at
 * @property bool $licence_watch Checked daily until active
 * @property CarbonImmutable|null $licence_found_at
 * @property CarbonImmutable|null $switched_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class LicenceJourney extends Model
{
    protected $fillable = ['user_id', 'state_code', 'steps', 'application_filed_on', 'exam_on', 'exam_time', 'test_centre', 'kit', 'attempts', 'weak_topics', 'reminded', 'passed_on', 'news', 'offers_opt_in', 'offers_city', 'offers_opted_at', 'licence_number', 'licence_check', 'licence_checked_at', 'licence_watch', 'licence_found_at', 'switched_at'];

    protected function casts(): array
    {
        return [
            'steps' => 'array', 'kit' => 'array', 'attempts' => 'array', 'weak_topics' => 'array', 'reminded' => 'array',
            'application_filed_on' => 'immutable_date', 'exam_on' => 'immutable_date', 'passed_on' => 'immutable_date', 'offers_opt_in' => 'boolean', 'offers_opted_at' => 'datetime', 'licence_checked_at' => 'datetime', 'licence_watch' => 'boolean', 'licence_found_at' => 'datetime', 'switched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
