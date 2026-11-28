<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Models\Organisation;
use App\Models\PolicyPassage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Studio\AdvertisingPolicy;
use App\VirtualBroker\PolicyKnowledge;
use App\VirtualBroker\PolicySearch;
use App\VirtualBroker\TexasConflicts;

/**
 * Brokerage rules (A15.2, broker journey screen 5, ADR 0071): rules the
 * brokerage writes in REOS beside its manual ("Agents send every new
 * listing to the office before it goes live"). Each is checked against the
 * TREC rules as it's written or changed: a conflict is flagged at once
 * with the TREC rule and its source, the rule is kept (it's the broker's
 * to change), and in every answer on that topic the TREC rule goes first.
 * The virtual broker answers from these rules like the FAQ.
 *
 * @phpstan-type Conflict array{rule: string, citation: string, url: string|null, excerpt: string, rule_text: string}
 * @phpstan-type RuleRow array{id: int, topic: string, text: string, conflict: Conflict|null, updated_at: string|null}
 * @phpstan-type PoliciesView array{manual: array{version: int, file_name: string, conflicts: int}|null, advertising: array{approval_new_agents: bool, approval_all: bool, banned: int}, rules: list<RuleRow>, can_manage: bool}
 */
final readonly class BrokerageRules
{
    public function __construct(private PolicySearch $search, private AuditLogger $audit, private PolicyKnowledge $knowledge, private AdvertisingPolicy $advertising, private BrokerageStaff $staff) {}

    /**
     * Brokerage › Policies: the manual, the advertising policy and the
     * brokerage's own rules in one place.
     *
     * @return PoliciesView
     */
    public function view(Organisation $brokerage, User $viewer): array
    {
        $manual = $this->knowledge->current($brokerage);
        $ads = $this->advertising->rules($brokerage);

        return [
            'manual' => $manual === null ? null : ['version' => $manual->version, 'file_name' => $manual->file_name, 'conflicts' => count($manual->conflicts ?? [])],
            'advertising' => ['approval_new_agents' => $ads['approval_new_agents'], 'approval_all' => $ads['approval_all'], 'banned' => count($ads['banned'])],
            'rules' => $this->list($brokerage),
            'can_manage' => $this->staff->can($viewer, $brokerage, 'manage_policies'),
        ];
    }

    /** @return list<RuleRow> */
    public function list(Organisation $brokerage): array
    {
        return array_values(PolicyPassage::query()->where('organisation_id', $brokerage->id)->where('kind', PolicyPassage::POLICY)
            ->latest('updated_at')->limit(200)->get()->map(fn (PolicyPassage $p): array => self::row($p))->all());
    }

    /**
     * The first TREC rule the text contradicts, if any (used as the broker types).
     *
     * @return Conflict|null
     */
    public function check(Organisation $brokerage, string $text): ?array
    {
        return TexasConflicts::first($text, $this->search->stateRules(($brokerage->states ?? [])[0] ?? 'TX'));
    }

    /** @return RuleRow */
    public function add(Organisation $brokerage, User $actor, string $topic, string $text): array
    {
        $rule = PolicyPassage::query()->create([
            'organisation_id' => $brokerage->id, 'kind' => PolicyPassage::POLICY, 'heading' => mb_substr(trim($topic), 0, 300),
            'content' => trim($text), 'position' => 0, 'conflict' => $this->check($brokerage, $text),
        ]);
        $this->audit->record('policy_rule.added', 'policy_passage', (string) $rule->id, ['topic' => $rule->heading, 'conflict' => $rule->conflict['rule'] ?? null], $actor, $brokerage->id);

        return self::row($rule);
    }

    /** @return RuleRow */
    public function update(Organisation $brokerage, User $actor, int $id, string $topic, string $text): array
    {
        $rule = $this->find($brokerage, $id);
        $rule->forceFill(['heading' => mb_substr(trim($topic), 0, 300), 'content' => trim($text), 'conflict' => $this->check($brokerage, $text)])->save();
        $this->audit->record('policy_rule.changed', 'policy_passage', (string) $rule->id, ['topic' => $rule->heading, 'conflict' => $rule->conflict['rule'] ?? null], $actor, $brokerage->id);

        return self::row($rule);
    }

    /** Removes a rule, or puts it back (Undo). */
    public function remove(Organisation $brokerage, User $actor, int $id, bool $restore = false): void
    {
        $query = PolicyPassage::query()->where('organisation_id', $brokerage->id)->where('kind', PolicyPassage::POLICY);
        $rule = ($restore ? $query->onlyTrashed() : $query)->findOrFail($id);
        $restore ? $rule->restore() : $rule->delete();
        $this->audit->record($restore ? 'policy_rule.restored' : 'policy_rule.removed', 'policy_passage', (string) $rule->id, ['topic' => $rule->heading], $actor, $brokerage->id);
    }

    private function find(Organisation $brokerage, int $id): PolicyPassage
    {
        return PolicyPassage::query()->where('organisation_id', $brokerage->id)->where('kind', PolicyPassage::POLICY)->findOrFail($id);
    }

    /** @return RuleRow */
    private static function row(PolicyPassage $p): array
    {
        return ['id' => $p->id, 'topic' => (string) $p->heading, 'text' => $p->content, 'conflict' => $p->conflict, 'updated_at' => $p->updated_at?->toIso8601String()];
    }
}
