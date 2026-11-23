<?php

declare(strict_types=1);

namespace App\StateRules;

/**
 * The shape of a rule's value (A5.2). Each type has one editor on screen 62
 * and one validator, so a rule can't be saved in a shape the engine can't read.
 */
enum RuleType: string
{
    /** `{"number": 18}` with the unit from the catalogue (hours, months, years, dollars). */
    case Number = 'number';
    /** `{"courses": [{"code": "legal_update_1", "title": "Legal Update I", "hours": 4}]}` */
    case Courses = 'courses';
    /** `{"code": "broker_responsibility", "title": "…", "hours": 6, "roles": ["sponsoring_broker", …]}` */
    case RoleCourse = 'role_course';
    /** `{"qualifying_hours": 90, "courses": ["legal_update_1", "legal_update_2"]}` */
    case FirstRenewal = 'first_renewal';
    /** `{"fee_usd": 200, "days": 60}` */
    case FeeDays = 'fee_days';
    /** `{"text": "…"}`: a rule people read (advertising, brokerage). */
    case Text = 'text';
    /** `{"max_hours_per_day": 12, "presence_percent": 100, "makeup_min_percent": 67, "makeup_days": 90}` (A7.1) */
    case SeatTime = 'seat_time';

    /**
     * Validation rules for the value.
     *
     * @return array<string, list<string>>
     */
    public function validation(): array
    {
        return match ($this) {
            self::Number => ['value.number' => ['required', 'numeric', 'min:0', 'max:100000']],
            self::Courses => [
                'value.courses' => ['required', 'array', 'min:1', 'max:20'],
                'value.courses.*.code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
                'value.courses.*.title' => ['required', 'string', 'max:160'],
                'value.courses.*.hours' => ['required', 'numeric', 'min:0', 'max:200'],
            ],
            self::RoleCourse => [
                'value.code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
                'value.title' => ['required', 'string', 'max:160'],
                'value.hours' => ['required', 'numeric', 'min:0', 'max:200'],
                'value.roles' => ['required', 'array', 'min:1'],
                'value.roles.*' => ['required', 'string', 'in:sponsoring_broker,designated_broker,delegated_supervisor'],
            ],
            self::FirstRenewal => [
                'value.qualifying_hours' => ['required', 'numeric', 'min:0', 'max:1000'],
                'value.courses' => ['present', 'array'],
                'value.courses.*' => ['string', 'max:60'],
            ],
            self::FeeDays => [
                'value.fee_usd' => ['required', 'numeric', 'min:0', 'max:100000'],
                'value.days' => ['required', 'integer', 'min:0', 'max:3650'],
            ],
            self::Text => ['value.text' => ['required', 'string', 'max:2000']],
            self::SeatTime => [
                'value.max_hours_per_day' => ['required', 'numeric', 'min:1', 'max:24'],
                'value.presence_percent' => ['required', 'integer', 'min:0', 'max:100'],
                'value.makeup_min_percent' => ['required', 'integer', 'min:0', 'max:100'],
                'value.makeup_days' => ['required', 'integer', 'min:0', 'max:365'],
            ],
        };
    }
}
