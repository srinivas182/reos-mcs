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
            // The licence journey (A10.3a, screen 16): the state's steps, and how long an application stays open.
            'licensing.steps' => ['topic' => RuleTopic::Licensing, 'type' => RuleType::Steps, 'used_by' => ['licence_journey']],
            'licensing.application_months' => ['topic' => RuleTopic::Licensing, 'type' => RuleType::Number, 'unit' => 'months', 'used_by' => ['licence_journey']],
            'exam.vendor' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Text, 'used_by' => ['licence_journey', 'test_prep']],
            // The licensing exam's format per portion (A9.1): test prep simulates it.
            'exam.national.questions' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'questions', 'used_by' => ['test_prep']],
            'exam.national.scored' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'questions', 'used_by' => ['test_prep']],
            'exam.national.minutes' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'minutes', 'used_by' => ['test_prep']],
            'exam.national.pass' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'correct', 'used_by' => ['test_prep']],
            'exam.state.questions' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'questions', 'used_by' => ['test_prep']],
            'exam.state.scored' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'questions', 'used_by' => ['test_prep']],
            'exam.state.minutes' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'minutes', 'used_by' => ['test_prep']],
            'exam.state.pass' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'correct', 'used_by' => ['test_prep']],
            // Retakes and exam day (A10.3a, screens 14 and 19): attempts before more education, and the testing vendor's rules.
            'exam.attempts' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Number, 'unit' => 'attempts', 'used_by' => ['licence_journey']],
            'exam.day_rules' => ['topic' => RuleTopic::Exam, 'type' => RuleType::Text, 'used_by' => ['licence_journey']],
            'ce.period_months' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Number, 'unit' => 'months', 'used_by' => ['ce_card', 'broker_compliance', 'reminders']],
            'ce.hours' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Number, 'unit' => 'hours', 'used_by' => ['ce_card', 'broker_compliance', 'course_catalogue']],
            'ce.mandatory_courses' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Courses, 'used_by' => ['ce_card', 'broker_compliance', 'course_catalogue']],
            'ce.broker_responsibility' => ['topic' => RuleTopic::Ce, 'type' => RuleType::RoleCourse, 'used_by' => ['ce_card', 'broker_compliance', 'course_catalogue']],
            'ce.first_renewal' => ['topic' => RuleTopic::Ce, 'type' => RuleType::FirstRenewal, 'used_by' => ['ce_card', 'broker_compliance']],
            'ce.no_repeat' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Text, 'used_by' => ['ce_card', 'course_catalogue']],
            'ce.commission_meeting' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Number, 'unit' => 'hours', 'used_by' => ['ce_card']],
            'ce.deferral' => ['topic' => RuleTopic::Ce, 'type' => RuleType::FeeDays, 'used_by' => ['ce_card', 'reminders']],
            'ce.renewal_steps' => ['topic' => RuleTopic::Ce, 'type' => RuleType::Steps, 'used_by' => ['ce_wallet']],
            'advertising.license_holder_name' => ['topic' => RuleTopic::Advertising, 'type' => RuleType::Text, 'used_by' => ['post_checks', 'virtual_broker']],
            'advertising.broker_name_size' => ['topic' => RuleTopic::Advertising, 'type' => RuleType::Text, 'used_by' => ['post_checks', 'virtual_broker']],
            'advertising.social_media' => ['topic' => RuleTopic::Advertising, 'type' => RuleType::Text, 'used_by' => ['post_checks', 'virtual_broker']],
            'advertising.misleading_titles' => ['topic' => RuleTopic::Advertising, 'type' => RuleType::Text, 'used_by' => ['post_checks', 'virtual_broker']],
            'agents.grow_closings' => ['topic' => RuleTopic::Agents, 'type' => RuleType::Number, 'unit' => 'closings', 'used_by' => ['agent_modes']],
            'agents.lead_closings' => ['topic' => RuleTopic::Agents, 'type' => RuleType::Number, 'unit' => 'closings', 'used_by' => ['agent_modes']],
            'agents.own_years' => ['topic' => RuleTopic::Agents, 'type' => RuleType::Number, 'unit' => 'years', 'used_by' => ['agent_modes']],
            'courses.seat_time' => ['topic' => RuleTopic::Courses, 'type' => RuleType::SeatTime, 'used_by' => ['course_approvals', 'course_player', 'live_classes']],
            'brokerage.sponsorship' => ['topic' => RuleTopic::Brokerage, 'type' => RuleType::Text, 'used_by' => ['brokerage_matching', 'licence_journey', 'virtual_broker']],
            // What the virtual broker cites (A12.1): trust money, compensation, supervision, IABS and the promulgated forms.
            'brokerage.trust_money' => ['topic' => RuleTopic::Brokerage, 'type' => RuleType::Text, 'used_by' => ['virtual_broker', 'contract_review']],
            'brokerage.compensation' => ['topic' => RuleTopic::Brokerage, 'type' => RuleType::Text, 'used_by' => ['virtual_broker']],
            'brokerage.supervision' => ['topic' => RuleTopic::Brokerage, 'type' => RuleType::Text, 'used_by' => ['virtual_broker']],
            'brokerage.iabs' => ['topic' => RuleTopic::Brokerage, 'type' => RuleType::Text, 'used_by' => ['virtual_broker']],
            'forms.promulgated' => ['topic' => RuleTopic::Forms, 'type' => RuleType::Text, 'used_by' => ['virtual_broker', 'contract_review']],
        ];
    }

    /** @return array{topic: RuleTopic, type: RuleType, unit?: string, used_by: list<string>}|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Features that read rules today (the rest arrive in their sprints). */
    public const LIVE_FEATURES = ['ce_card', 'course_approvals', 'test_prep', 'licence_journey', 'live_classes', 'agent_modes', 'ce_wallet', 'virtual_broker'];
}
