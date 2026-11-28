<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Communities;

use App\Communities\Communities;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communities\AttendanceRequest;
use App\Http\Requests\Communities\CommunityActionRequest;
use App\Http\Requests\Communities\CommunityRequest;
use App\Http\Requests\Communities\JoinRequest;
use App\Http\Requests\Communities\PinRequest;
use App\Http\Requests\Communities\PostRequest;
use App\Http\Requests\Communities\ResourceRequest;
use App\Http\Requests\Communities\SessionRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Communities (B17.2, ADR 0086): a Pro or Partner provider's community of
 * verified agents and providers, with posts, resources and expert
 * sessions; invites by link and QR code; results for the owner.
 */
final class CommunityController extends Controller
{
    /**
     * My communities.
     *
     * @response array{data: list<array{id: string, name: string, owner: string, members: int, next: array{title: string, starts_at: string}|null}>}
     */
    public function mine(#[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => $communities->mine($user)]);
    }

    /**
     * My community (the owner).
     *
     * Set up on first use, for Pro and Partner providers (422 `plan` otherwise). With the invite link and results.
     *
     * @response array{data: array{id: string, name: string, about: string|null, owner: string, is_owner: bool, member: bool, members: int, invite_url: string|null, qr_url: string|null, posts: list<array{id: string, author: string, body: string, pinned: bool, at: string, mine: bool}>, resources: list<array{id: string, title: string, url: string}>, sessions: list<array{id: string, title: string, about: string|null, format: string, starts_at: string, minutes: int, place: string|null, join_url: string|null, capacity: int|null, price_cents: int, ce_course: string|null, status: string, going: int, attended: int, mine: string|null}>}, results: array{members: int, joined_30: int, via: array{link: int, qr: int}, posts_30: int, sessions: int, rsvps: int, attended: int, show_rate: int, top: list<array{title: string, going: int, attended: int}>}}
     */
    public function own(#[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $community = $communities->forOwner($user);

        return response()->json(['data' => $communities->view($user, $community->uuid), 'results' => $communities->results($user)]);
    }

    /**
     * Rename or describe my community.
     *
     * @response array{data: array{id: string, name: string, about: string|null}}
     */
    public function update(CommunityRequest $request, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $about = $request->filled('about') ? $request->string('about')->trim()->toString() : null;
        $community = $communities->update($user, $request->string('name')->trim()->toString(), $about);

        return response()->json(['data' => ['id' => $community->uuid, 'name' => $community->name, 'about' => $community->about]]);
    }

    /**
     * A new invite link and QR code.
     *
     * The old link stops working.
     *
     * @response array{data: array{invite_url: string}}
     */
    public function newCode(CommunityActionRequest $request, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => ['invite_url' => Communities::inviteUrl($communities->newCode($user))]]);
    }

    /**
     * An invite, before joining.
     *
     * Whether the person can join: verified agents and providers only.
     *
     * @response array{data: array{id: string, name: string, about: string|null, owner: string, members: int, verified: bool, member: bool}}
     */
    public function preview(string $code, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => $communities->preview($user, $code)]);
    }

    /**
     * Join by link or QR code.
     *
     * 422 `code` for someone who isn't a verified agent or provider.
     *
     * @response array{data: array{id: string}}
     */
    public function join(JoinRequest $request, string $code, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => ['id' => $communities->join($user, $code, $request->string('via', 'link')->toString())->uuid]]);
    }

    /**
     * A community.
     *
     * Members only: posts (pinned first), resources and sessions. A session's join link shows once you're going.
     *
     * @response array{data: array{id: string, name: string, about: string|null, owner: string, is_owner: bool, member: bool, members: int, invite_url: string|null, qr_url: string|null, posts: list<array{id: string, author: string, body: string, pinned: bool, at: string, mine: bool}>, resources: list<array{id: string, title: string, url: string}>, sessions: list<array{id: string, title: string, about: string|null, format: string, starts_at: string, minutes: int, place: string|null, join_url: string|null, capacity: int|null, price_cents: int, ce_course: string|null, status: string, going: int, attended: int, mine: string|null}>}}
     */
    public function show(string $community, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => $communities->view($user, $community)]);
    }

    /**
     * Leave a community.
     *
     * @response array{left: bool}
     */
    public function leave(CommunityActionRequest $request, string $community, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $communities->leave($user, $community);

        return response()->json(['left' => true]);
    }

    /**
     * Post.
     *
     * Checked first: advertising wording, no pay for referrals, no phone numbers, email addresses or links (422 `body` with the reason).
     *
     * @status 201
     *
     * @response array{data: array{id: string}}
     */
    public function post(PostRequest $request, string $community, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => ['id' => $communities->post($user, $community, $request->string('body')->toString())->uuid]], 201);
    }

    /**
     * Take a post down.
     *
     * Its author or the community's owner.
     *
     * @response array{removed: bool}
     */
    public function removePost(CommunityActionRequest $request, string $post, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $communities->removePost($user, $post);

        return response()->json(['removed' => true]);
    }

    /**
     * Pin a post (the owner).
     *
     * @response array{pinned: bool}
     */
    public function pin(PinRequest $request, string $post, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $communities->pin($user, $post, $request->boolean('pinned'));

        return response()->json(['pinned' => $request->boolean('pinned')]);
    }

    /**
     * Add a resource (the owner).
     *
     * @status 201
     *
     * @response array{data: array{id: string, title: string, url: string}}
     */
    public function addResource(ResourceRequest $request, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $r = $communities->addResource($user, $request->string('title')->trim()->toString(), $request->string('url')->toString());

        return response()->json(['data' => ['id' => $r->uuid, 'title' => $r->title, 'url' => $r->url]], 201);
    }

    /**
     * Remove a resource (the owner).
     *
     * @response array{removed: bool}
     */
    public function removeResource(CommunityActionRequest $request, string $resource, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $communities->removeResource($user, $resource);

        return response()->json(['removed' => true]);
    }

    /**
     * Schedule an expert session (the owner).
     *
     * Members are told. Free on Pro; a price needs Partner (422 `price_cents`). A CE course number only when the session is approved for CE elsewhere; otherwise it's information only.
     *
     * @status 201
     *
     * @response array{data: array{id: string}}
     */
    public function schedule(SessionRequest $request, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => ['id' => $communities->schedule($user, $request->values())->uuid]], 201);
    }

    /**
     * Cancel a session (the owner).
     *
     * @response array{cancelled: bool}
     */
    public function cancelSession(CommunityActionRequest $request, string $session, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $communities->cancelSession($user, $session);

        return response()->json(['cancelled' => true]);
    }

    /**
     * Who's going (the host).
     *
     * @response array{data: list<array{id: int, name: string, attended: bool}>}
     */
    public function roster(string $session, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => $communities->roster($user, $session)]);
    }

    /**
     * Who came (the host).
     *
     * Marks the session done.
     *
     * @response array{data: list<array{id: int, name: string, attended: bool}>}
     */
    public function attendance(AttendanceRequest $request, string $session, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $communities->attendance($user, $session, $request->ids());

        return response()->json(['data' => $communities->roster($user, $session)]);
    }

    /**
     * Results (the owner).
     *
     * @response array{data: array{members: int, joined_30: int, via: array{link: int, qr: int}, posts_30: int, sessions: int, rsvps: int, attended: int, show_rate: int, top: list<array{title: string, going: int, attended: int}>}}
     */
    public function results(#[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => $communities->results($user)]);
    }

    /**
     * RSVP to a session.
     *
     * Free: going. Paid: held, with the checkout link; going once paid. 422 `session` when it's full or over.
     *
     * @response array{data: array{status: string, checkout_url: string|null}}
     */
    public function rsvp(CommunityActionRequest $request, string $session, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        return response()->json(['data' => $communities->rsvp($user, $session)]);
    }

    /**
     * Cancel my RSVP.
     *
     * @response array{cancelled: bool}
     */
    public function cancelRsvp(CommunityActionRequest $request, string $session, #[CurrentUser] User $user, Communities $communities): JsonResponse
    {
        $communities->cancelRsvp($user, $session);

        return response()->json(['cancelled' => true]);
    }
}
