<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderCategory;
use App\Models\User;
use App\ProviderNetwork\ProviderProfiles;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The provider profile on the web (B15.3): the builder (provider screen 7)
 * and the agent's view, a verified profile and the list of verified
 * providers.
 */
final class ProfilePagesController extends Controller
{
    public function builder(#[CurrentUser] User $user, ProviderProfiles $profiles): Response
    {
        return Inertia::render('provider/profile', ['builder' => $profiles->builder($user)]);
    }

    public function index(Request $request, ProviderProfiles $profiles): Response
    {
        $category = $request->query('category');
        $place = $request->query('place');
        $category = is_string($category) && $category !== '' ? $category : null;
        $place = is_string($place) && $place !== '' ? mb_substr($place, 0, 80) : null;

        return Inertia::render('providers/index', [
            'providers' => $profiles->directory($category, $place, app()->getLocale()),
            'filters' => ['category' => $category, 'place' => $place],
            'categories' => ProviderCategory::query()->where('status', 'live')->orderBy('name')->get(['key', 'name'])->map(static fn (ProviderCategory $c): array => ['key' => $c->key, 'name' => $c->name])->values()->all(),
        ]);
    }

    public function show(int $profile, #[CurrentUser] User $user, ProviderProfiles $profiles): Response
    {
        return Inertia::render('providers/show', ['provider' => $profiles->show($user, $profile, app()->getLocale())]);
    }
}
