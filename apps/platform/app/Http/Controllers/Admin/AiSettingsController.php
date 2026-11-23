<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Price;
use App\Models\User;
use App\Services\Ai\AiSettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI and credits (web journey screen 69, A6.1): the answer order per role,
 * the model and data rules, the credit cost of each AI action and the
 * limits. Customer data is never used for training (fixed).
 */
final class AiSettingsController extends Controller
{
    public function show(AiSettings $settings): Response
    {
        $pack = Price::query()->with('product')->current()->whereHas('product', static fn ($q) => $q->where('kind', 'credit_pack')->where('status', 'live'))->orderBy('amount_cents')->first();

        return Inertia::render('admin/ai/index', [
            'settings' => $settings->current(),
            'versions' => DB::table('ai_settings')->count(),
            'credit_pack' => $pack === null ? null : ['credits' => $pack->product->credits, 'amount' => $pack->amount_cents],
            'options' => ['sources' => AiSettings::SOURCES, 'roles' => AiSettings::ROLES, 'models' => AiSettings::MODELS, 'actions' => array_keys(AiSettings::COSTS)],
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $actor, AiSettings $settings): RedirectResponse
    {
        $rules = [
            'answer_order' => ['required', 'array'],
            'model' => ['required', Rule::in(AiSettings::MODELS)],
            'costs' => ['required', 'array'],
            'default_monthly_credits' => ['required', 'integer', 'min:0', 'max:100000'],
            'candidate_monthly_credits' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'hourly_limit' => ['required', 'integer', 'min:1', 'max:10000'],
        ];

        foreach (AiSettings::ROLES as $role) {
            $rules["answer_order.{$role}"] = ['required', 'array', 'min:1', 'max:3'];
            $rules["answer_order.{$role}.*"] = ['required', 'distinct', Rule::in(AiSettings::SOURCES)];
        }

        foreach (array_keys(AiSettings::COSTS) as $action) {
            $rules["costs.{$action}"] = ['required', 'integer', 'min:0', 'max:1000'];
        }

        $data = $request->validate($rules);
        $order = [];
        $costs = [];

        foreach (AiSettings::ROLES as $role) {
            $steps = $data['answer_order'][$role] ?? [];
            $order[$role] = array_values(array_filter(is_array($steps) ? $steps : [], is_string(...)));
        }

        foreach (array_keys(AiSettings::COSTS) as $action) {
            $cost = $data['costs'][$action] ?? 0;
            $costs[$action] = is_numeric($cost) ? (int) $cost : 0;
        }

        $settings->save([
            'answer_order' => $order,
            'model' => is_string($data['model']) ? $data['model'] : 'claude-sonnet',
            'costs' => $costs,
            'default_monthly_credits' => is_numeric($data['default_monthly_credits']) ? (int) $data['default_monthly_credits'] : 0,
            'candidate_monthly_credits' => is_numeric($data['candidate_monthly_credits'] ?? null) ? (int) $data['candidate_monthly_credits'] : $settings->current()['candidate_monthly_credits'],
            'hourly_limit' => is_numeric($data['hourly_limit']) ? (int) $data['hourly_limit'] : 200,
        ], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('aiSettings.saved')]);

        return back();
    }
}
