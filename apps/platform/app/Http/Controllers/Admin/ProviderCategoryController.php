<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProviderCategoryRequest;
use App\Models\ProviderCategory;
use App\Models\ProviderCategoryPrice;
use App\Models\State;
use App\Models\User;
use App\Services\ProviderCategories;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Service provider categories (web journey screen 68, A6.1): unlimited
 * categories, each with its licence check, profile fields, permissions and
 * flat monthly plan prices per state. The provider app reads them.
 */
final class ProviderCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/provider-categories/index', [
            'categories' => ProviderCategory::query()->with('prices')
                ->orderByRaw("CASE status WHEN 'live' THEN 0 WHEN 'draft' THEN 1 WHEN 'requested' THEN 2 ELSE 3 END")->orderBy('name')->get()
                ->map(static fn (ProviderCategory $c): array => [
                    'id' => $c->id,
                    'key' => $c->key,
                    'name' => $c->name,
                    'description' => $c->description,
                    'licence_check' => $c->licence_check,
                    'verification' => $c->verification,
                    'profile_fields' => $c->profile_fields ?? [],
                    'special_permissions' => $c->special_permissions,
                    'areas_on_pro' => $c->areas_on_pro,
                    'status' => $c->status,
                    'requests' => $c->requests,
                    // Providers sign up with the provider app (Sprint 9); none yet.
                    'providers' => 0,
                    'prices' => $c->prices->map(static fn (ProviderCategoryPrice $p): array => ['state' => $p->state_code, 'tier' => $p->tier, 'monthly' => $p->monthly_cents])->values()->all(),
                ])->values()->all(),
            'options' => [
                'tiers' => ProviderCategory::TIERS,
                'statuses' => ProviderCategory::STATUSES,
                'states' => State::query()->orderBy('name')->get(['code', 'name'])->map(static fn (State $s): array => ['code' => $s->code, 'name' => $s->name])->values()->all(),
            ],
        ]);
    }

    public function store(ProviderCategoryRequest $request, #[CurrentUser] User $actor, ProviderCategories $categories): RedirectResponse
    {
        $categories->save(null, Arr::except($request->validated(), ['prices']), $request->prices(), $actor);

        return back();
    }

    public function update(ProviderCategoryRequest $request, ProviderCategory $category, #[CurrentUser] User $actor, ProviderCategories $categories): RedirectResponse
    {
        $categories->save($category, Arr::except($request->validated(), ['prices']), $request->prices(), $actor);

        return back();
    }
}
