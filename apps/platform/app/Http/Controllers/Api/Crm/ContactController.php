<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Crm;

use App\Crm\Contacts;
use App\Crm\CrmWriteBack;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\PhoneContactsRequest;
use App\Http\Resources\Crm\ContactActivityResource;
use App\Http\Resources\Crm\ContactNoteResource;
use App\Http\Resources\Crm\ContactResource;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Your contacts (B7.1, ADR 0027): one person, all sources. Contacts from
 * your CRMs and your phone are matched on email and phone number, never on
 * a name alone; when two sources disagree, the newest change wins and the
 * other value stays in Sync activity.
 */
final class ContactController extends Controller
{
    /** List your contacts, newest change first. */
    public function index(#[CurrentUser] User $user, Contacts $contacts): AnonymousResourceCollection
    {
        return ContactResource::collection($contacts->of(Connection::OWNER_USER, $user->id));
    }

    /**
     * Sync activity (screen 23).
     *
     * What changed in your contacts and where it came from, newest first, and
     * possible duplicates to merge.
     *
     * @response array{data: list<array{id: int, kind: string, contact: array{id: string, name: string}, source: string, source_name: string, text: string, occurred_at: string}>, suggestions: list<array{id: int, contact: array{id: string, name: string}, other: array{id: string, name: string}}>}
     */
    public function activity(#[CurrentUser] User $user, Contacts $contacts, Request $request): JsonResponse
    {
        return response()->json([
            'data' => ContactActivityResource::collection($contacts->activity(Connection::OWNER_USER, $user->id))->toArray($request),
            'suggestions' => array_map(static fn (array $s): array => [
                'id' => $s['id'],
                'contact' => ['id' => $s['contact']->uuid, 'name' => $s['contact']->name()],
                'other' => ['id' => $s['other']->uuid, 'name' => $s['other']->name()],
            ], $contacts->suggestions(Connection::OWNER_USER, $user->id)),
        ]);
    }

    /**
     * Add your phone's contacts.
     *
     * Read on the phone, with your permission, in batches of up to 500. They
     * are matched like any other source and never written back to the phone.
     *
     * @response array{received: int, contacts: int}
     */
    public function phone(PhoneContactsRequest $request, #[CurrentUser] User $user, Contacts $contacts): JsonResponse
    {
        $received = $request->contacts();

        foreach ($received as $record) {
            $contacts->receive(Connection::OWNER_USER, $user->id, Contacts::PHONE, null, $record);
        }

        return response()->json(['received' => count($received), 'contacts' => Contact::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->count()]);
    }

    /** Stop using your phone's contacts: they are removed from REOS unless another source also has them. */
    public function forgetPhone(#[CurrentUser] User $user, Contacts $contacts): Response
    {
        $contacts->forget(Connection::OWNER_USER, $user->id, Contacts::PHONE);

        return response()->noContent();
    }

    /** Merge another contact into this one. Every source link is kept, and the merge can be split again. */
    public function merge(Request $request, string $contact, #[CurrentUser] User $user, Contacts $contacts): ContactResource
    {
        $other = (string) $request->validate(['other' => ['required', 'uuid']])['other'];
        $merged = $contacts->merge($this->find($user, $contact), $this->find($user, $other), $user);

        return new ContactResource($merged->load(['links', 'tags']));
    }

    /**
     * One contact: every source that knows them, and their notes, newest first.
     *
     * @response array{data: array<string, mixed>, notes: list<array{id: int, body: string, source: string, source_name: string, sent_to: list<string>, at: string|null}>}
     */
    public function show(string $contact, #[CurrentUser] User $user, Request $request): JsonResponse
    {
        $found = $this->find($user, $contact)->load(['links', 'tags']);
        $notes = $found->notes()->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => (new ContactResource($found))->toArray($request), 'notes' => ContactNoteResource::collection($notes)->toArray($request)]);
    }

    /**
     * Add a note.
     *
     * Saved at once and sent to every connected CRM that knows this person.
     *
     * @status 201
     */
    public function note(Request $request, string $contact, #[CurrentUser] User $user, CrmWriteBack $writeBack): ContactNoteResource
    {
        $body = (string) $request->validate(['body' => ['required', 'string', 'min:1', 'max:20000']])['body'];

        return new ContactNoteResource($writeBack->note($this->find($user, $contact), $body, $user));
    }

    /** Change the stage; sent to the person's connected CRMs. */
    public function update(Request $request, string $contact, #[CurrentUser] User $user, CrmWriteBack $writeBack): ContactResource
    {
        $stage = (string) $request->validate(['stage' => ['required', 'string', 'min:1', 'max:60']])['stage'];

        return new ContactResource($writeBack->stage($this->find($user, $contact), $stage, $user)->load(['links', 'tags']));
    }

    /** Undo a merge: the contact that was merged in comes back on its own. */
    public function split(string $contact, #[CurrentUser] User $user, Contacts $contacts): ContactResource
    {
        $found = Contact::onlyTrashed()->where('uuid', $contact)->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->whereNotNull('merged_into_id')->first();
        abort_if($found === null, 404);

        return new ContactResource($contacts->split($found, $user)->load(['links', 'tags']));
    }

    /** "Not the same person": dismiss a suggested merge. */
    public function dismiss(int $suggestion, #[CurrentUser] User $user, Contacts $contacts): Response
    {
        $contacts->dismiss($suggestion, Connection::OWNER_USER, $user->id);

        return response()->noContent();
    }

    private function find(User $user, string $uuid): Contact
    {
        $contact = Contact::query()->where('uuid', $uuid)->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->first();
        abort_if($contact === null, 404);

        return $contact;
    }
}
