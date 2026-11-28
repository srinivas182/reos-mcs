<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\User;
use App\ProviderNetwork\AgentDirectory;
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

    public function index(Request $request, #[CurrentUser] User $user, ProviderProfiles $profiles, AgentDirectory $directory): Response
    {
        $category = $request->query('category');
        $place = $request->query('place');
        $category = is_string($category) && $category !== '' ? $category : null;
        $place = is_string($place) && $place !== '' ? mb_substr($place, 0, 80) : null;

        return Inertia::render('providers/index', [
            'providers' => $profiles->directory($category, $place, app()->getLocale()),
            'filters' => ['category' => $category, 'place' => $place],
            // Providers near you, with ratings and connections (B16.3).
            'near' => $directory->near($user, $category),
            'categories' => ProviderCategory::query()->where('status', 'live')->orderBy('name')->get(['key', 'name'])->map(static fn (ProviderCategory $c): array => ['key' => $c->key, 'name' => $c->name])->values()->all(),
        ]);
    }

    public function show(int $profile, #[CurrentUser] User $user, ProviderProfiles $profiles, AgentDirectory $directory): Response
    {
        $card = $profiles->show($user, $profile, app()->getLocale());

        // Reviews, my connection, and Request to connect (B16.3); none for a provider's own preview.
        return Inertia::render('providers/show', ['provider' => $card, 'network' => $card['live'] && $user->id !== ProviderProfile::query()->whereKey($profile)->value('user_id') ? $directory->forProfile($user, $profile) : null]);
    }
}
