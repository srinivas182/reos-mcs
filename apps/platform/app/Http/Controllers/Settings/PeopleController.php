<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\People\PersonResource;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Authorization\PeopleDirectory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Settings › People (super admin): find a person, see and change their roles. */
final class PeopleController extends Controller
{
    public function index(Request $request, PeopleDirectory $directory): Response
    {
        $search = $request->string('search')->trim()->limit(100, '')->toString();
        $people = $directory->search($search);

        return Inertia::render('settings/people/index', [
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
        ]);
    }

    public function show(Request $request, User $person): Response
    {
        return Inertia::render('settings/people/show', [
            'person' => (new PersonResource($person))->resolve($request),
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
