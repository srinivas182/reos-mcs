<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * How AI behaves and what it costs (screen 69, A6.1): the answer order per
 * role (what is tried first, second, third), the answer model, the credit
 * cost of each AI action, the default monthly credits per seat, candidates'
 * free monthly credits for Ask beyond their course (A10.1) and the abuse limit. Every save is a new version (`ai_settings`); the newest is in
 * force. Customer data is never used for training: that rule is fixed, not
 * a setting.
 */
final readonly class AiSettings
{
    /** What an answer can come from, in the order a role tries them. */
    public const SOURCES = ['course_knowledge', 'brokerage_policy', 'state_rules', 'category_knowledge', 'general_ai', 'instructor_booking', 'escalate_broker', 'reos_support'];

    public const ROLES = ['candidate', 'agent', 'broker', 'provider'];

    /** Models REOS may answer with (all through Amazon Bedrock in us-east-2). */
    public const MODELS = ['claude-sonnet', 'claude-haiku'];

    /** AI actions and what each costs by default. */
    public const COSTS = [
        'knowledge_answer' => 0,
        'general_answer' => 2,
        'contract_review' => 20,
        'content_post' => 5,
        'short_video' => 30,
        'ai_lesson_minute' => 10,
        'ai_suggestion' => 1,
        'voice_todo' => 1,
    ];

    public function __construct(private AuditLogger $audit) {}

    /** @return array{answer_order: array<string, list<string>>, model: string, costs: array<string, int>, default_monthly_credits: int, candidate_monthly_credits: int, hourly_limit: int} */
    public function defaults(): array
    {
        return [
            'answer_order' => [
                'candidate' => ['course_knowledge', 'general_ai', 'instructor_booking'],
                'agent' => ['brokerage_policy', 'general_ai', 'escalate_broker'],
                'broker' => ['brokerage_policy', 'state_rules', 'general_ai'],
                'provider' => ['category_knowledge', 'general_ai', 'reos_support'],
            ],
            'model' => 'claude-sonnet',
            'costs' => self::COSTS,
            'default_monthly_credits' => max(0, config()->integer('reos.ai.monthly_credits', 500)),
            // Candidates' free credits each month for Ask beyond their course (A10.1, 23 November 2026); then their sponsor's pool, then their own.
            'candidate_monthly_credits' => 1000,
            'hourly_limit' => 200,
        ];
    }

    /** @return array{answer_order: array<string, list<string>>, model: string, costs: array<string, int>, default_monthly_credits: int, candidate_monthly_credits: int, hourly_limit: int} */
    public function current(): array
    {
        $saved = DB::table('ai_settings')->latest('id')->value('settings');
        $decoded = is_string($saved) ? json_decode($saved, true) : null;
        $defaults = $this->defaults();

        if (! is_array($decoded)) {
            return $defaults;
        }

        $order = $defaults['answer_order'];
        $savedOrder = is_array($decoded['answer_order'] ?? null) ? $decoded['answer_order'] : [];
        $savedCosts = is_array($decoded['costs'] ?? null) ? $decoded['costs'] : [];

        foreach (self::ROLES as $role) {
            $steps = $savedOrder[$role] ?? null;

            if (is_array($steps)) {
                $order[$role] = array_values(array_filter($steps, static fn (mixed $s): bool => is_string($s) && in_array($s, self::SOURCES, true)));
            }
        }

        $costs = $defaults['costs'];

        foreach (array_keys(self::COSTS) as $action) {
            $cost = $savedCosts[$action] ?? null;

            if (is_int($cost)) {
                $costs[$action] = $cost;
            }
        }

        $model = $decoded['model'] ?? null;
        $monthly = $decoded['default_monthly_credits'] ?? null;
        $hourly = $decoded['hourly_limit'] ?? null;
        $candidate = $decoded['candidate_monthly_credits'] ?? null;

        return [
            'answer_order' => $order,
            'model' => is_string($model) && in_array($model, self::MODELS, true) ? $model : $defaults['model'],
            'costs' => $costs,
            'default_monthly_credits' => is_int($monthly) ? $monthly : $defaults['default_monthly_credits'],
            'candidate_monthly_credits' => is_int($candidate) ? $candidate : $defaults['candidate_monthly_credits'],
            'hourly_limit' => is_int($hourly) ? $hourly : $defaults['hourly_limit'],
        ];
    }

    public function cost(string $action): int
    {
        return $this->current()['costs'][$action] ?? 1;
    }

    /** @param  array{answer_order: array<string, list<string>>, model: string, costs: array<string, int>, default_monthly_credits: int, candidate_monthly_credits: int, hourly_limit: int}  $settings */
    public function save(array $settings, User $actor): void
    {
        $before = $this->current();

        if ($before === $settings) {
            return;
        }

        DB::table('ai_settings')->insert(['settings' => json_encode($settings, JSON_THROW_ON_ERROR), 'created_by' => $actor->id, 'created_at' => now()]);
        $changed = array_keys(array_filter($settings, static fn (mixed $value, string $key): bool => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));
        $this->audit->record('ai.settings_changed', 'ai_settings', 'platform', [
            'before' => array_intersect_key($before, array_flip($changed)),
            'after' => array_intersect_key($settings, array_flip($changed)),
        ], $actor);
    }
}
