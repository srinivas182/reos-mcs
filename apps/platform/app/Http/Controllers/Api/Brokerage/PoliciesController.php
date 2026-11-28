<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\BrokerageAccess;
use App\Brokerage\BrokerageRules;
use App\Brokerage\BrokerageStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\BrokerageRuleRequest;
use App\Http\Requests\Brokerage\PolicyCheckRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Brokerage › Policies (A15.2, ADR 0071): the manual, the advertising policy, and rules checked against TREC as they're written. */
final class PoliciesController extends Controller
{
    /**
     * The brokerage's policies.
     *
     * The current manual and its TREC conflicts, the advertising policy, and the brokerage's own rules, each with the TREC rule it contradicts if any.
     *
     * @response array{data: array{manual: array{version: int, file_name: string, conflicts: int}|null, advertising: array{approval_new_agents: bool, approval_all: bool, banned: int}, rules: list<array{id: int, topic: string, text: string, conflict: array{rule: string, citation: string, url: string|null, excerpt: string, rule_text: string}|null, updated_at: string|null}>, can_manage: bool}}
     */
    public function index(#[CurrentUser] User $user, BrokerageAccess $access, BrokerageRules $rules): JsonResponse
    {
        return response()->json(['data' => $rules->view($access->brokerage($user), $user)]);
    }

    /**
     * Check words against TREC.
     *
     * The first TREC rule the words contradict, with its source, or null. Used while a rule is being written.
     *
     * @response array{data: array{conflict: array{rule: string, citation: string, url: string|null, excerpt: string, rule_text: string}|null}}
     */
    public function check(PolicyCheckRequest $request, #[CurrentUser] User $user, BrokerageAccess $access, BrokerageRules $rules): JsonResponse
    {
        return response()->json(['data' => ['conflict' => $rules->check($access->brokerage($user), $request->string('text')->toString())]]);
    }

    /**
     * Add a brokerage rule.
     *
     * Checked against TREC at once. A conflicting rule is kept and flagged; in answers on that topic the TREC rule goes first.
     *
     * @response array{data: array{id: int, topic: string, text: string, conflict: array{rule: string, citation: string, url: string|null, excerpt: string, rule_text: string}|null, updated_at: string|null}}
     */
    public function store(BrokerageRuleRequest $request, #[CurrentUser] User $user, BrokerageAccess $access, BrokerageRules $rules, BrokerageStaff $staff): JsonResponse
    {
        $brokerage = $access->brokerage($user);
        $staff->allow($user, $brokerage, 'manage_policies');

        return response()->json(['data' => $rules->add($brokerage, $user, $request->string('topic')->toString(), $request->string('text')->toString())]);
    }

    /**
     * Change a brokerage rule.
     *
     * @response array{data: array{id: int, topic: string, text: string, conflict: array{rule: string, citation: string, url: string|null, excerpt: string, rule_text: string}|null, updated_at: string|null}}
     */
    public function update(BrokerageRuleRequest $request, int $rule, #[CurrentUser] User $user, BrokerageAccess $access, BrokerageRules $rules, BrokerageStaff $staff): JsonResponse
    {
        $brokerage = $access->brokerage($user);
        $staff->allow($user, $brokerage, 'manage_policies');

        return response()->json(['data' => $rules->update($brokerage, $user, $rule, $request->string('topic')->toString(), $request->string('text')->toString())]);
    }

    /** Remove a brokerage rule. */
    public function destroy(int $rule, #[CurrentUser] User $user, BrokerageAccess $access, BrokerageRules $rules, BrokerageStaff $staff): Response
    {
        $brokerage = $access->brokerage($user);
        $staff->allow($user, $brokerage, 'manage_policies');
        $rules->remove($brokerage, $user, $rule);

        return response()->noContent();
    }

    /** Put a removed brokerage rule back (Undo). */
    public function restore(int $rule, #[CurrentUser] User $user, BrokerageAccess $access, BrokerageRules $rules, BrokerageStaff $staff): Response
    {
        $brokerage = $access->brokerage($user);
        $staff->allow($user, $brokerage, 'manage_policies');
        $rules->remove($brokerage, $user, $rule, restore: true);

        return response()->noContent();
    }
}
