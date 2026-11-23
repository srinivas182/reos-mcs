<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Crm;

use App\Crm\ContactHub;
use App\Crm\CrmWriteBack;
use App\Crm\Insights\ContactInsights;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ContactTaskRequest;
use App\Http\Requests\Crm\ContactTouchRequest;
use App\Http\Resources\Crm\ContactInsightResource;
use App\Http\Resources\Crm\ContactNoteResource;
use App\Http\Resources\Crm\ContactResource;
use App\Http\Resources\Todos\TodoResource;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Working a contact (CRM hub, B8.3, ADR 0034): the AI summary and next
 * step (free for agents, within the hourly AI limit), a task that goes to
 * the CRM, and "called" or "texted" from your own phone, logged to your
 * CRMs. Today's "do this now" (Agent App Journey screen 6).
 */
final class ContactWorkController extends Controller
{
    /**
     * AI summary and next step.
     *
     * Written from the contact's notes, tasks and activity in every source,
     * and kept until the contact changes. Free for agents; over the hourly
     * AI limit you get 429.
     *
     * @response array{data: array{summary: string, next_step: string, why: string, channel: 'call'|'text'|'email'|'note', draft: string|null, engine: 'ai'|'simulator', generated_at: string}}
     */
    public function insight(string $contact, #[CurrentUser] User $user, ContactHub $hub, ContactInsights $insights, Request $request): JsonResponse
    {
        return response()->json(['data' => (new ContactInsightResource($insights->generate($this->find($hub, $user, $contact), $user)))->toArray($request)]);
    }

    /**
     * Add a task.
     *
     * A to-do about the person, sent to the CRM that knows them and synced
     * most recently (`sent_to` is null when it stays in REOS).
     *
     * @status 201
     *
     * @response array{data: array<string, mixed>, sent_to: string|null}
     */
    public function task(ContactTaskRequest $request, string $contact, #[CurrentUser] User $user, ContactHub $hub, CrmWriteBack $writeBack): JsonResponse
    {
        [$todo, $sentTo] = $writeBack->task($this->find($hub, $user, $contact), $request->title(), $request->due(), $user);

        return response()->json(['data' => (new TodoResource($todo->refresh()))->toArray($request), 'sent_to' => $sentTo], 201);
    }

    /**
     * Log a call or text.
     *
     * You called or texted from your own phone: saved as a note and sent to
     * every connected CRM that knows the person.
     *
     * @status 201
     */
    public function touch(ContactTouchRequest $request, string $contact, #[CurrentUser] User $user, ContactHub $hub, CrmWriteBack $writeBack): ContactNoteResource
    {
        return new ContactNoteResource($writeBack->touch($this->find($hub, $user, $contact), $request->channel(), $request->body(), $user));
    }

    /**
     * Do this now (Today).
     *
     * The one contact most in need of a touch (an overdue task first), with
     * the AI next step and why, or null when there's none.
     *
     * @response array{data: array{contact: array<string, mixed>, insight: array{summary: string, next_step: string, why: string, channel: string, draft: string|null, engine: string, generated_at: string}}|null}
     */
    public function today(#[CurrentUser] User $user, ContactInsights $insights, Request $request): JsonResponse
    {
        $today = $insights->today($user);

        return response()->json(['data' => $today === null ? null : [
            'contact' => (new ContactResource($today['contact']->load(['links', 'tags'])))->toArray($request),
            'insight' => (new ContactInsightResource($today['insight']))->toArray($request),
        ]]);
    }

    private function find(ContactHub $hub, User $user, string $uuid): Contact
    {
        return $hub->find($user, $uuid) ?? abort(404);
    }
}
