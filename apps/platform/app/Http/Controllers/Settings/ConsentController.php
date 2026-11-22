<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ConsentRequest;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Privacy\ConsentChoices;
use App\Services\Privacy\Consents;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings › Privacy (A6.3) for every role: the privacy switches (provider
 * network, recruiting, marketing email, texts, AI help with your data) and
 * "Your consents", each with what was agreed, which version, when and how,
 * and Withdraw.
 */
final class ConsentController extends Controller
{
    public function edit(#[CurrentUser] User $user, Consents $consents): Response
    {
        return Inertia::render('settings/privacy', [
            ...$consents->summary($user),
            'versions' => ['terms' => ConsentPurpose::Terms->version(), 'privacy_notice' => ConsentPurpose::PrivacyNotice->version()],
        ]);
    }

    public function update(ConsentRequest $request, ConsentPurpose $purpose, #[CurrentUser] User $user, ConsentChoices $choices): RedirectResponse
    {
        $choices->change($user, $purpose, $request->boolean('granted'), $request->subject(), ConsentMethod::Web);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('consents.saved')]);

        return back();
    }
}
