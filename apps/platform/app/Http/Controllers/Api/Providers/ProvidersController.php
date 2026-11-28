<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Providers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\ProviderNetwork\ProviderProfiles;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verified providers, as agents see them (B15.3, ADR 0075): live profiles
 * with the licence badge and when it was checked, the areas and services.
 */
final class ProvidersController extends Controller
{
    /**
     * Verified providers.
     *
     * Live providers with a published profile, Partner first.
     *
     * @queryParam category string A category key, such as loan-officer.
     * @queryParam place string A city or a ZIP code the provider serves.
     *
     * @response array{data: list<array{id: int, name: string, category: string|null, photo_url: string|null, logo_url: string|null, bio: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>, licence: array{kind: string, number: string|null, verified: bool, checked_at: string|null, via: string|null}, areas: list<string>, tier: string|null, live: bool}>}
     */
    public function index(Request $request, ProviderProfiles $profiles): JsonResponse
    {
        $category = $request->query('category');
        $place = $request->query('place');

        return response()->json(['data' => $profiles->directory(is_string($category) && $category !== '' ? $category : null, is_string($place) ? mb_substr($place, 0, 80) : null, app()->getLocale())]);
    }

    /**
     * A provider's profile.
     *
     * A live provider's published profile, or my own preview.
     *
     * @response array{data: array{id: int, name: string, category: string|null, photo_url: string|null, logo_url: string|null, bio: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>, licence: array{kind: string, number: string|null, verified: bool, checked_at: string|null, via: string|null}, areas: list<string>, tier: string|null, live: bool}}
     */
    public function show(int $profile, #[CurrentUser] User $user, ProviderProfiles $profiles): JsonResponse
    {
        return response()->json(['data' => $profiles->show($user, $profile, app()->getLocale())]);
    }
}
