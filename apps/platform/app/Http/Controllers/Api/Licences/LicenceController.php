<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Licences;

use App\Http\Controllers\Controller;
use App\Http\Requests\Licences\AddLicenceRequest;
use App\Http\Requests\Licences\LicenceRoleRequest;
use App\Http\Resources\Licences\LicenceResource;
use App\Models\Licence;
use App\Models\User;
use App\Services\Licences\Licences;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * My licences (A5.1; agent journey screens 148 and 149): every state licence,
 * each checked with that state's regulator or approved by a reviewer.
 */
final class LicenceController extends Controller
{
    /** List my licences, live states first. */
    public function index(#[CurrentUser] User $user, Licences $licences): AnonymousResourceCollection
    {
        return LicenceResource::collection($licences->of($user));
    }

    /**
     * Add a licence.
     *
     * Checks the number with the state's regulator. A match in your name
     * counts at once (`check: verified`); a different name, a state with no
     * check yet, or (with `review: true`) a number the regulator doesn't have
     * goes to a reviewer. Without `review`, an unknown number is refused (422).
     *
     * @response 201 array{data: LicenceResource, check: 'verified'|'name_mismatch'|'not_found'|'no_lookup', source: string|null}
     */
    public function store(AddLicenceRequest $request, #[CurrentUser] User $user, Licences $licences): JsonResponse
    {
        [$licence, $check, $source] = $licences->add($user, $request->state(), $request->number(), $request->type(), $request->role(), $request->askReview());

        return response()->json([
            'data' => (new LicenceResource($licence))->resolve($request),
            'check' => $check->value,
            'source' => $source,
        ], 201);
    }

    /** Set who you supervise (changes your CE). */
    public function update(LicenceRoleRequest $request, #[CurrentUser] User $user, int $licence, Licences $licences): LicenceResource
    {
        return new LicenceResource($licences->setRole($this->find($user, $licence), $request->role(), $user)->load('state'));
    }

    /** Remove a licence (restore undoes it). */
    public function destroy(#[CurrentUser] User $user, int $licence, Licences $licences): Response
    {
        $licences->remove($this->find($user, $licence), $user);

        return response()->noContent();
    }

    /** Restore a removed licence. */
    public function restore(#[CurrentUser] User $user, int $licence, Licences $licences): LicenceResource
    {
        $found = $this->find($user, $licence, trashed: true);
        abort_unless($found->trashed(), 404);

        return new LicenceResource($licences->restore($found, $user)->load('state'));
    }

    private function find(User $user, int $id, bool $trashed = false): Licence
    {
        return ($trashed ? Licence::withTrashed() : Licence::query())->where('user_id', $user->id)->findOrFail($id);
    }
}
