<?php

declare(strict_types=1);

namespace App\StateRules;

/**
 * Every rule REOS knows how to use (A5.2): its topic, value shape and unit,
 * and the features that read it ("where used" on screen 62). A state fills in
 * values; the engine reads them by key.
 */
final class RuleCatalogue
{
    /**
     * @return array<string, array{topic: RuleTopic, type: RuleType, unit?: string, used_by: list<string>}>
     */
    public static function all(): array
    {
        return [
            'licensing.qualifying_hours' => ['topic' => RuleTopic::Licensing, 'type' => RuleType::Number, 'unit' => 'hours', 'used_by' => ['candidate_plan', 'course_catalogue']],
            'licensing.qualifying_courses' => ['topic' => RuleTopic::Licensing, 'type' => RuleType::Courses, 'used_by' => ['candidate_plan', 'course_catalogue']],
            'licensing.minimum_age' => ['topic' => RuleTopic::Licensing, 'type' => RuleType::Number, 'unit' => 'years', 'used_by' => ['licence_journey']],
            'exam.vendor' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Text, 'used_by' => ['licence_journey', 'test_prep']],
            'ce.period_months' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Number, 'unit' => 'months', 'used_by' => ['ce_card', 'broker_compliance', 'reminders']],
            'ce.hours' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Number, 'unit' => 'hours', 'used_by' => ['ce_card', 'broker_compliance', 'course_catalogue']],
            'ce.mandatory_courses' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Courses, 'used_by' => ['ce_card', 'broker_compliance', 'course_catalogue']],
            'ce.broker_responsibility' => ['topic' => RuleTopic::Ce, 'type' => RuleType::RoleCourse, 'used_by' => ['ce_card', 'broker_compliance', 'course_catalogue']],
            'ce.first_renewal' => ['topic' => RuleTopic::Ce, 'type' => RuleType::FirstRenewal, 'used_by' => ['ce_card', 'broker_compliance']],
            'ce.no_repeat' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Text, 'used_by' => ['ce_card', 'course_catalogue']],
            'ce.commission_meeting' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Number, 'unit' => 'hours', 'used_by' => ['ce_card']],
            'ce.deferral' => ['topic' => RuleTopic::Ce, 'type' => RuleType::FeeDays, 'used_by' => ['ce_card', 'reminders']],
            'advertising.license_holder_name' => ['topic' => RuleTopic::Advertising, 'type' => RuleType::Text, 'used_by' => ['post_checks']],
            'advertising.broker_name_size' => ['topic' => RuleTopic::Advertising, 'type' => RuleType::Text, 'used_by' => ['post_checks']],
            'advertising.social_media' => ['topic' => RuleTopic::Advertising, 'type' => RuleType::Text, 'used_by' => ['post_checks']],
            'advertising.misleading_titles' => ['topic' => RuleTopic::Advertising, 'type' => RuleType::Text, 'used_by' => ['post_checks']],
            'brokerage.sponsorship' => ['topic' => RuleTopic::Brokerage, 'type' => RuleType::Text, 'used_by' => ['brokerage_matching', 'licence_journey']],
        ];
    }

    /** @return array{topic: RuleTopic, type: RuleType, unit?: string, used_by: list<string>}|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Features that read rules today (the rest arrive in their sprints). */
    public const LIVE_FEATURES = ['ce_card'];
}
