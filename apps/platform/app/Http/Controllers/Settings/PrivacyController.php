<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Privacy\PrivacyRequests;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** "See my data" and "Correct my data" on Settings › Profile (A4.3b, TDPSA). */
final class PrivacyController extends Controller
{
    public function store(Request $request, #[CurrentUser] User $user, PrivacyRequests $privacy): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in([PrivacyRequest::ACCESS, PrivacyRequest::CORRECT])],
            'details' => ['nullable', 'required_if:kind,correct', 'string', 'max:2000'],
        ]);

        $privacy->submit($user, (string) $validated['kind'], isset($validated['details']) && is_string($validated['details']) ? $validated['details'] : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('privacy.sent.'.$validated['kind'])]);

        return back();
    }

    /** The export, for its owner only, through the signed 7-day link. */
    public function download(PrivacyRequest $privacyRequest, #[CurrentUser] User $user): StreamedResponse
    {
        abort_unless($privacyRequest->user_id === $user->id && $privacyRequest->export_path !== null && Storage::exists($privacyRequest->export_path), 404);

        return Storage::download($privacyRequest->export_path, 'reos-my-data-'.$privacyRequest->completed_at?->format('Y-m-d').'.json', [
            'Content-Type' => 'application/json',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }
}
