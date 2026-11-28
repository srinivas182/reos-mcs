<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\ApprovalsQueue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\ApprovalActionRequest;
use App\Http\Requests\Brokerage\ApprovalSettingsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * One approvals queue (A15.3, ADR 0072): agents' posts and public pages
 * waiting for approval and their escalated questions, oldest first.
 *
 * @phpstan-import-type Queue from ApprovalsQueue
 */
final class ApprovalsController extends Controller
{
    /**
     * The approvals queue.
     *
     * What the signed-in broker can clear: everything for the designated broker; an office admin's duties; a team leader's team. Each item says how many working hours it has waited and whether it's past the brokerage's limit.
     *
     * @response array{data: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, fallback_hours: int, can: array{posts: bool, questions: bool, settings: bool}, counts: array{posts: int, pages: int, questions: int, overdue: int}, items: list<array{kind: 'post'|'page'|'question', id: string, agent: string, summary: string, waiting_since: string|null, hours: float, overdue: bool, detail: array<string, mixed>}>}}
     */
    public function index(#[CurrentUser] User $user, ApprovalsQueue $queue): JsonResponse
    {
        return response()->json(['data' => $queue->for($user)]);
    }

    /**
     * Clear one item.
     *
     * Approve or return (with a note) a post or page; answer a question, optionally adding the answer to the brokerage's knowledge. Returns the queue.
     *
     * @param  'post'|'page'|'question'  $kind
     *
     * @response array{data: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, fallback_hours: int, can: array{posts: bool, questions: bool, settings: bool}, counts: array{posts: int, pages: int, questions: int, overdue: int}, items: list<array{kind: 'post'|'page'|'question', id: string, agent: string, summary: string, waiting_since: string|null, hours: float, overdue: bool, detail: array<string, mixed>}>}}
     */
    public function act(ApprovalActionRequest $request, string $kind, string $item, #[CurrentUser] User $user, ApprovalsQueue $queue): JsonResponse
    {
        $text = $request->input('text');

        return response()->json(['data' => $queue->act($user, $kind, $item, $request->action(), is_string($text) ? $text : null, $request->boolean('add_to_knowledge'))]);
    }

    /**
     * Set the fallback limit.
     *
     * How many working hours (8 AM to 6 PM Central, Monday to Friday) an item may wait before the office admins are told. The designated broker only.
     *
     * @response array{data: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, fallback_hours: int, can: array{posts: bool, questions: bool, settings: bool}, counts: array{posts: int, pages: int, questions: int, overdue: int}, items: list<array{kind: 'post'|'page'|'question', id: string, agent: string, summary: string, waiting_since: string|null, hours: float, overdue: bool, detail: array<string, mixed>}>}}
     */
    public function settings(ApprovalSettingsRequest $request, #[CurrentUser] User $user, ApprovalsQueue $queue): JsonResponse
    {
        return response()->json(['data' => $queue->setFallbackHours($user, $request->integer('fallback_hours'))]);
    }
}
