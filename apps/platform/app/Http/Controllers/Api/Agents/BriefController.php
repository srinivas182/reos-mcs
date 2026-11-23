<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agents;

use App\Agents\AgentBriefs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\BriefSettingsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** The morning brief and its settings (A11.1c, ADR 0044; agent screen 184). */
final class BriefController extends Controller
{
    /**
     * Today's morning brief.
     *
     * The spoken script (and transcript), its sections, the person to call first and the commands offered at the end.
     *
     * @response array{data: array{date: string, sections: list<array{key: string, count: int, text: string}>, first_call: array{name: string, phone: string, contact: string}|null, commands: list<array{kind: 'call'|'deal'|'today', label: string, phone: string|null, contact: string|null, deal: string|null}>, script: list<string>}}
     */
    public function show(#[CurrentUser] User $user, AgentBriefs $briefs): JsonResponse
    {
        return response()->json(['data' => $briefs->for($user)]);
    }

    /**
     * Morning brief settings.
     *
     * @response array{data: array{enabled: bool, at: string, timezone: string, sections: list<'todos'|'appointments'|'deadlines'|'leads'|'ce'>}}
     */
    public function settings(#[CurrentUser] User $user, AgentBriefs $briefs): JsonResponse
    {
        return response()->json(['data' => $briefs->settings($user)]);
    }

    /**
     * Change when the brief goes out and what it covers.
     *
     * @response array{data: array{enabled: bool, at: string, timezone: string, sections: list<'todos'|'appointments'|'deadlines'|'leads'|'ce'>}}
     */
    public function update(BriefSettingsRequest $request, #[CurrentUser] User $user, AgentBriefs $briefs): JsonResponse
    {
        return response()->json(['data' => $briefs->save($user, $request->boolean('enabled'), $request->string('at')->toString(), $request->string('timezone')->toString(), $request->sections())]);
    }
}
