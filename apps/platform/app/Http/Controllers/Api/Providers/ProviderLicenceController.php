<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Providers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\ProviderLicenceRequest;
use App\Licences\ProviderLicences;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * A service provider's professional licences (B10.3, ADR 0043): TREC
 * inspector and TDI licences checked in seconds against the regulators'
 * data; NMLS IDs checked by a REOS reviewer.
 */
final class ProviderLicenceController extends Controller
{
    /**
     * My professional licences.
     *
     * @response array{data: list<array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}>}
     */
    public function index(#[CurrentUser] User $user, ProviderLicences $licences): JsonResponse
    {
        return response()->json(['data' => $licences->of($user)]);
    }

    /**
     * Add or replace my professional licence.
     *
     * `trec_inspector` and `tdi` are checked against the regulator's public data at once: verified when active and in my name, otherwise a reviewer checks. `nmls` always goes to a reviewer.
     *
     * @response array{data: array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}}
     */
    public function store(ProviderLicenceRequest $request, #[CurrentUser] User $user, ProviderLicences $licences): JsonResponse
    {
        return response()->json(['data' => $licences->add($user, $request->kind(), $request->string('number')->toString())]);
    }
}
