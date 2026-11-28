<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Brokerage\BrokerageStaff;
use App\Http\Controllers\Controller;
use App\Mls\AgentPages;
use App\Models\User;
use App\Services\StateRules\StateRules;
use App\Social\PostApprovals;
use App\StateRules\RuleTopic;
use App\Studio\AdvertisingPolicy;
use App\Tenancy\Brand;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Advertising policy (B12.1, ADR 0050; broker web screen 35, broker journey
 * screen 6): the Texas rules every post is checked against (locked), the
 * brokerage's own rules (approval for new agents or everyone, banned
 * phrases), the brand kit the designs use, and posts waiting for approval.
 * Changes apply at once and are audited.
 */
final class AdvertisingController extends Controller
{
    public function index(#[CurrentUser] User $user, AdvertisingPolicy $policy, PostApprovals $approvals, StateRules $rules, AgentPages $pages): Response
    {
        $brokerage = $approvals->brokerageOf($user);
        $state = ($brokerage->states ?? [])[0] ?? 'TX';
        $texas = [];

        foreach ($rules->inForce($state, CarbonImmutable::now()) as $rule) {
            if ($rule->topic === RuleTopic::Advertising && is_string($rule->value['text'] ?? null)) {
                $texas[] = ['key' => $rule->key, 'text' => $rule->value['text'], 'citation' => $rule->citation, 'url' => $rule->source_url];
            }
        }

        $brand = Brand::of($brokerage);

        return Inertia::render('organisation/advertising', [
            'brokerage' => $brokerage->name,
            'texas' => $texas,
            'checks' => ['fair_housing' => count(AdvertisingPolicy::FAIR_HOUSING), 'claims' => count(AdvertisingPolicy::CLAIMS)],
            'rules' => $policy->rules($brokerage),
            'brand' => ['colour' => $brand->primary?->hex, 'logo' => $brand->toArray()['logo_url'] ?? null],
            'pending' => $approvals->pending($brokerage),
            // Public pages waiting for the broker (B13.2).
            'pages' => $pages->pending($brokerage),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user, AdvertisingPolicy $policy, PostApprovals $approvals): RedirectResponse
    {
        $data = $request->validate([
            'approval_new_agents' => ['required', 'boolean'],
            'approval_all' => ['required', 'boolean'],
            'banned' => ['present', 'array', 'max:100'],
            'banned.*' => ['nullable', 'string', 'max:80'],
        ]);
        $banned = array_values(array_filter(is_array($data['banned']) ? $data['banned'] : [], is_string(...)));
        app(BrokerageStaff::class)->allow($user, $approvals->brokerageOf($user), 'manage_policies');
        $policy->save($approvals->brokerageOf($user), $request->boolean('approval_new_agents'), $request->boolean('approval_all'), $banned, $user);

        return back()->with('success', __('studio.policy.saved'));
    }

    public function approve(string $post, #[CurrentUser] User $user, PostApprovals $approvals): RedirectResponse
    {
        app(BrokerageStaff::class)->allow($user, $approvals->brokerageOf($user), 'approve_posts');
        $approvals->approve($user, $approvals->brokerageOf($user), $post);

        return back()->with('success', __('studio.policy.approved'));
    }

    public function sendBack(Request $request, string $post, #[CurrentUser] User $user, PostApprovals $approvals): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        app(BrokerageStaff::class)->allow($user, $approvals->brokerageOf($user), 'approve_posts');
        $approvals->sendBack($user, $approvals->brokerageOf($user), $post, $request->string('note')->toString());

        return back()->with('success', __('studio.policy.returned'));
    }

    public function approvePage(string $page, #[CurrentUser] User $user, PostApprovals $approvals, AgentPages $pages): RedirectResponse
    {
        app(BrokerageStaff::class)->allow($user, $approvals->brokerageOf($user), 'approve_posts');
        $pages->decide($user, $approvals->brokerageOf($user), $page, true);

        return back()->with('success', __('studio.policy.approved'));
    }

    public function returnPage(Request $request, string $page, #[CurrentUser] User $user, PostApprovals $approvals, AgentPages $pages): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        app(BrokerageStaff::class)->allow($user, $approvals->brokerageOf($user), 'approve_posts');
        $pages->decide($user, $approvals->brokerageOf($user), $page, false, $request->string('note')->toString());

        return back()->with('success', __('studio.policy.returned'));
    }
}
