<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Onboarding\WaitlistRequest;
use App\Http\Resources\Onboarding\StateResource;
use App\Models\State;
use App\Models\User;
use App\Services\Onboarding\States;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** "Where do you want to be licensed?" and the waitlist for states not open yet. */
final class StateController extends Controller
{
    /**
     * List states.
     *
     * Live states first. The super admin decides which are live.
     *
     * @unauthenticated
     */
    public function index(States $states): AnonymousResourceCollection
    {
        return StateResource::collection($states->all());
    }

    /**
     * Join a state's waitlist.
     *
     * Once per person, or per device before an account exists. Members are
     * told the day the state opens.
     *
     * @unauthenticated
     *
     * @response array{waiting: int}
     */
    public function waitlist(WaitlistRequest $request, State $state, States $states): JsonResponse
    {
        $user = $request->user('sanctum');

        return response()->json([
            'waiting' => $states->join(
                $state,
                $user instanceof User ? $user : null,
                $request->filled('device_id') ? $request->string('device_id')->toString() : null,
                $request->filled('email') ? $request->string('email')->toString() : null,
            ),
        ]);
    }
}
