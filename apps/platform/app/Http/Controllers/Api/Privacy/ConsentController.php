<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Privacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ConsentRequest;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Privacy\ConsentChoices;
use App\Services\Privacy\Consents;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ConsentController extends Controller
{
    /**
     * My privacy switches and consents (A6.3, Me › Privacy).
     *
     * `switches`: each privacy switch, on or off, and whether the person
     * chose it (otherwise it's the default for their role). `records`: every
     * consent given or withdrawn, newest first, with the version of the words,
     * how (`web`, `app`, `admin`, `sms`) and whether it can be withdrawn.
     *
     * @response array{switches: list<array{purpose: 'marketing_email'|'sms'|'ai_personal_data'|'provider_network'|'recruiting', on: bool, chosen: bool, at: string|null}>, records: list<array{id: int, purpose: 'terms'|'privacy_notice'|'marketing_email'|'sms'|'session_recording'|'contact_sharing'|'ai_personal_data'|'provider_network'|'recruiting', subject: string|null, granted: bool, version: string, method: 'web'|'app'|'admin'|'sms', by_admin: bool, at: string, current: bool, withdrawable: bool}>}
     */
    public function index(#[CurrentUser] User $user, Consents $consents): JsonResponse
    {
        return response()->json($consents->summary($user));
    }

    /**
     * Change a privacy switch or withdraw a consent (A6.3).
     *
     * Switches take `granted` true or false. Consents for one thing (a
     * session recording, contact sharing with a provider) can only be
     * withdrawn here, with their `subject`. The terms and privacy notice go
     * only with the account (422).
     *
     * @response array{switches: list<array{purpose: 'marketing_email'|'sms'|'ai_personal_data'|'provider_network'|'recruiting', on: bool, chosen: bool, at: string|null}>, records: list<array{id: int, purpose: 'terms'|'privacy_notice'|'marketing_email'|'sms'|'session_recording'|'contact_sharing'|'ai_personal_data'|'provider_network'|'recruiting', subject: string|null, granted: bool, version: string, method: 'web'|'app'|'admin'|'sms', by_admin: bool, at: string, current: bool, withdrawable: bool}>}
     */
    public function update(ConsentRequest $request, ConsentPurpose $purpose, #[CurrentUser] User $user, ConsentChoices $choices, Consents $consents): JsonResponse
    {
        $choices->change($user, $purpose, $request->boolean('granted'), $request->subject(), ConsentMethod::App);

        return response()->json($consents->summary($user));
    }
}
