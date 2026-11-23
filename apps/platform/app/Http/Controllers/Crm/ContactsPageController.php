<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Authorization\Role;
use App\Crm\ContactHub;
use App\Crm\CrmWriteBack;
use App\Crm\DefaultCrm;
use App\Crm\Insights\ContactInsights;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\AddContactRequest;
use App\Http\Requests\Crm\ContactListRequest;
use App\Http\Requests\Crm\ContactTaskRequest;
use App\Http\Requests\Crm\ContactTouchRequest;
use App\Http\Requests\Crm\DefaultCrmRequest;
use App\Http\Resources\Crm\ContactActivityResource;
use App\Http\Resources\Crm\ContactInsightResource;
use App\Http\Resources\Crm\ContactNoteResource;
use App\Http\Resources\Crm\ContactResource;
use App\Http\Resources\Crm\ContactRowResource;
use App\Http\Resources\Todos\TodoResource;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contacts, the CRM hub (agent web screen 13, B8.3): every contact from
 * every connected CRM and the phone in one list, by tab, tag or search,
 * with a contact panel to call, text, get an AI summary and add a note or
 * task, written back to the source CRMs. The agent's own contacts only.
 */
final class ContactsPageController extends Controller
{
    public function index(ContactListRequest $request, #[CurrentUser] User $user, ActiveRole $roles, ContactHub $hub, DefaultCrm $defaultCrm): Response
    {
        $this->agent($user, $roles);
        $list = $hub->list($user, $request->segmentName(), $request->tag(), $request->search());
        $selected = is_string($request->query('contact')) ? $hub->find($user, $request->query('contact')) : null;

        return Inertia::render('contacts/index', [
            'filters' => ['segment' => $request->segmentName(), 'tag' => $request->tag(), 'q' => $request->search()],
            'contacts' => ContactRowResource::collection($list['rows'])->toArray($request),
            'counts' => $list['counts'],
            'tags' => $list['tags'],
            'total' => $list['total'],
            'selected' => $selected === null ? null : $this->panel($request, $user, $hub, $selected),
            // Where new contacts go (screen 24, B8.3b).
            'crms' => $defaultCrm->options($user),
        ]);
    }

    /** Add contact: saved in REOS, and in the default CRM (B8.3b). */
    public function add(AddContactRequest $request, #[CurrentUser] User $user, ActiveRole $roles, CrmWriteBack $writeBack): RedirectResponse
    {
        $this->agent($user, $roles);
        [$contact, $sentTo] = $writeBack->addPerson($user, $request->person());

        return to_route('contacts.index', ['contact' => $contact->uuid])->with('success', $sentTo === null ? __('crm.add.saved') : __('crm.add.sent', ['tool' => $sentTo]));
    }

    /** Where new contacts go (screen 24). */
    public function defaultCrm(DefaultCrmRequest $request, #[CurrentUser] User $user, ActiveRole $roles, DefaultCrm $defaultCrm): RedirectResponse
    {
        $this->agent($user, $roles);
        $id = $request->connection();
        $choice = $id === null ? null : collect($defaultCrm->options($user))->firstWhere('connection', $id);
        abort_if($id !== null && ($choice === null || ! $choice['takes_new_people']), 422);
        $defaultCrm->set($user, $id === null ? null : Connection::query()->findOrFail($id));

        return back()->with('success', __('crm.add.defaultSaved'));
    }

    public function note(Request $request, string $contact, #[CurrentUser] User $user, ActiveRole $roles, ContactHub $hub, CrmWriteBack $writeBack): RedirectResponse
    {
        $body = (string) $request->validate(['body' => ['required', 'string', 'min:1', 'max:20000']])['body'];
        $writeBack->note($this->find($user, $roles, $hub, $contact), $body, $user);

        return back()->with('success', __('crm.contact.saved'));
    }

    public function task(ContactTaskRequest $request, string $contact, #[CurrentUser] User $user, ActiveRole $roles, ContactHub $hub, CrmWriteBack $writeBack): RedirectResponse
    {
        [, $sentTo] = $writeBack->task($this->find($user, $roles, $hub, $contact), $request->title(), $request->due(), $user);

        return back()->with('success', $sentTo === null ? __('crm.hub.taskSaved') : __('crm.hub.taskSent', ['tool' => $sentTo]));
    }

    public function insight(string $contact, #[CurrentUser] User $user, ActiveRole $roles, ContactHub $hub, ContactInsights $insights): RedirectResponse
    {
        $insights->generate($this->find($user, $roles, $hub, $contact), $user);

        return back();
    }

    public function touch(ContactTouchRequest $request, string $contact, #[CurrentUser] User $user, ActiveRole $roles, ContactHub $hub, CrmWriteBack $writeBack): RedirectResponse
    {
        $writeBack->touch($this->find($user, $roles, $hub, $contact), $request->channel(), $request->body(), $user);

        return back()->with('success', __('crm.hub.touchLogged'));
    }

    /** @return array<string, mixed> */
    private function panel(Request $request, User $user, ContactHub $hub, Contact $contact): array
    {
        $panel = $hub->panel($user, $contact->load(['links', 'tags']));

        return [
            'contact' => (new ContactResource($contact))->toArray($request),
            'address' => $contact->address,
            'notes' => ContactNoteResource::collection($panel['notes'])->toArray($request),
            'tasks' => TodoResource::collection($panel['tasks'])->toArray($request),
            'activity' => ContactActivityResource::collection($panel['activity'])->toArray($request),
            'insight' => $panel['insight'] === null ? null : (new ContactInsightResource($panel['insight']))->toArray($request),
            'segment' => $panel['segment'],
            'last_touch_at' => $panel['last_touch_at'],
        ];
    }

    private function find(User $user, ActiveRole $roles, ContactHub $hub, string $uuid): Contact
    {
        $this->agent($user, $roles);

        return $hub->find($user, $uuid) ?? abort(404);
    }

    /** Agents work their own contacts here; brokers never see them. */
    private function agent(User $user, ActiveRole $roles): void
    {
        abort_unless($roles->for($user)?->role === Role::Agent, 404);
    }
}
