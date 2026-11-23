<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\Licences\LicenceResource;
use App\Http\Resources\People\PersonResource;
use App\Licences\ProviderLicences;
use App\Models\AuditEvent;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\ProviderLicence;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Authorization\PeopleDirectory;
use App\Services\Licences\Licences;
use App\Services\Privacy\Consents;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Settings › People (super admin): find a person, see and change their roles. */
final class PeopleController extends Controller
{
    public function index(Request $request, PeopleDirectory $directory, Licences $licences, ProviderLicences $providerLicences): Response
    {
        $search = $request->string('search')->trim()->limit(100, '')->toString();
        $people = $directory->search($search);

        return Inertia::render('admin/people/index', [
            'search' => $search,
            'people' => [
                'data' => array_map(
                    static fn (User $user): array => (new PersonResource($user))->resolve($request),
                    $people->items(),
                ),
                'current_page' => $people->currentPage(),
                'last_page' => $people->lastPage(),
                'total' => $people->total(),
            ],
            // Licences waiting for a reviewer (A5.1), oldest first.
            'licenceReviews' => $licences->waitingForReview()->map(static fn (Licence $licence): array => [
                'id' => $licence->id,
                'person' => ['id' => $licence->user_id, 'name' => $licence->user->name ?? $licence->user->email ?? $licence->user->phone],
                'state' => $licence->state->name,
                'number' => $licence->number,
                'note' => $licence->review_note,
                'created_at' => $licence->created_at?->toIso8601String(),
            ])->all(),
            // Providers' licences waiting for a reviewer (B10.3): NMLS IDs, and numbers with no regulator match.
            'providerLicenceReviews' => $providerLicences->waitingForReview()->map(static fn (ProviderLicence $licence): array => [
                'id' => $licence->id,
                'person' => ['id' => $licence->user_id, 'name' => $licence->user->name ?? $licence->user->email ?? $licence->user->phone],
                'kind' => $licence->kind,
                'number' => $licence->number,
                'name_on_record' => $licence->name_on_record,
                'note' => $licence->review_note,
                'consumer_url' => ProviderLicences::consumerUrl($licence->kind, $licence->number),
                'created_at' => $licence->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function show(Request $request, User $person, Licences $licences, Consents $consents): Response
    {
        return Inertia::render('admin/people/show', [
            'person' => (new PersonResource($person))->resolve($request),
            // Their state licences; reviewers approve or reject those waiting (A5.1).
            'licences' => LicenceResource::collection($licences->of($person))->resolve($request),
            // Their privacy switches and consents (A6.3); switches can be changed on their behalf.
            'consents' => $consents->summary($person),
            // What can be granted where; the screen offers only valid pairs.
            'roleOptions' => array_map(static fn (Role $role): array => [
                'role' => $role->value,
                'organisationTypes' => array_map(static fn ($type): string => $type->value, $role->organisationTypes()),
            ], Role::cases()),
            'organisations' => Organisation::query()
                ->orderBy('name')
                ->get(['id', 'name', 'type'])
                ->map(static fn (Organisation $organisation): array => [
                    'id' => $organisation->id,
                    'name' => $organisation->name,
                    'type' => $organisation->type->value,
                ])
                ->all(),
            'activity' => AuditEvent::query()
                ->with('actor')
                ->where('subject_type', Memberships::AUDIT_SUBJECT)
                ->where('subject_id', (string) $person->id)
                ->whereIn('action', ['role.granted', 'role.removed'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(static fn (AuditEvent $event): array => (new AuditEventResource($event))->resolve($request))
                ->all(),
        ]);
    }
}
