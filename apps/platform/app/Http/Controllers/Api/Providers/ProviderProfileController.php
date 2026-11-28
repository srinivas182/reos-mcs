<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Providers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\ProfilePhotoRequest;
use App\Http\Requests\Providers\ProfileRequest;
use App\Models\User;
use App\ProviderNetwork\ProviderProfiles;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Build your profile (B15.3, provider screen 7, ADR 0075): photo and logo,
 * a bio in English and Spanish, services, specialities, a response-time
 * goal, free resources and expert sessions. Each save is checked by the
 * compliance engine before agents see it.
 */
final class ProviderProfileController extends Controller
{
    /**
     * My profile builder.
     *
     * The draft, the compliance issues with the last save, when it was last published, and the preview as agents see it.
     *
     * @response array{data: array{draft: array{bio: string|null, bio_es: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>}, issues: list<array{code: string, severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>, published_at: string|null, preview: array{id: int, name: string, category: string|null, photo_url: string|null, logo_url: string|null, bio: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>, licence: array{kind: string, number: string|null, verified: bool, checked_at: string|null, via: string|null}, areas: list<string>, tier: string|null, live: bool}|null, response_choices: list<int>}}
     */
    public function show(#[CurrentUser] User $user, ProviderProfiles $profiles): JsonResponse
    {
        return response()->json(['data' => $profiles->builder($user)]);
    }

    /**
     * Save my profile.
     *
     * Phone numbers, emails and web addresses are refused. Wording the compliance engine blocks stays a draft with its issues; agents keep seeing the last version that passed.
     *
     * @response array{data: array{draft: array{bio: string|null, bio_es: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>}, issues: list<array{code: string, severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>, published_at: string|null, preview: array{id: int, name: string, category: string|null, photo_url: string|null, logo_url: string|null, bio: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>, licence: array{kind: string, number: string|null, verified: bool, checked_at: string|null, via: string|null}, areas: list<string>, tier: string|null, live: bool}|null, response_choices: list<int>}}
     */
    public function update(ProfileRequest $request, #[CurrentUser] User $user, ProviderProfiles $profiles): JsonResponse
    {
        return response()->json(['data' => $profiles->save($user, $request->profile())]);
    }

    /**
     * Write my bio for me.
     *
     * A short bio in English and Spanish from my category, details, areas and services. Nothing is saved until I save the profile.
     *
     * @response array{data: array{bio: string, bio_es: string, engine: 'ai'|'simulator'}}
     */
    public function draft(#[CurrentUser] User $user, ProviderProfiles $profiles): JsonResponse
    {
        return response()->json(['data' => $profiles->draftBio($user)]);
    }

    /**
     * Upload my photo or logo.
     *
     * @response array{data: array{draft: array{bio: string|null, bio_es: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>}, issues: list<array{code: string, severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>, published_at: string|null, preview: array{id: int, name: string, category: string|null, photo_url: string|null, logo_url: string|null, bio: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>, licence: array{kind: string, number: string|null, verified: bool, checked_at: string|null, via: string|null}, areas: list<string>, tier: string|null, live: bool}|null, response_choices: list<int>}}
     */
    public function photo(ProfilePhotoRequest $request, #[CurrentUser] User $user, ProviderProfiles $profiles): JsonResponse
    {
        $profiles->photo($user, $request->string('kind')->toString(), $request->upload());

        return response()->json(['data' => $profiles->builder($user)]);
    }
}
