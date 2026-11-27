<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Compliance;

use App\Compliance\ComplianceEngine;
use App\Compliance\ComplianceLog;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The compliance engine for anything the agent sends (B13.1, ADR 0057):
 * check an email or text to a contact before it goes, and fix it
 * automatically. Posts, videos and the public page are checked where
 * they're made.
 */
final class ComplianceController extends Controller
{
    /**
     * Check before sending.
     *
     * Texas advertising rules, fair housing wording (with context exceptions and a second opinion for unclear wording) and the brokerage's phrases.
     *
     * @response array{data: array{ok: bool, issues: list<array{code: 'broker'|'broker_size'|'team_name'|'links'|'claims'|'title'|'fair_housing'|'unclear'|'banned'|'unlicensed', severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>}}
     */
    public function check(Request $request, #[CurrentUser] User $user, ComplianceEngine $engine, ComplianceLog $log): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(ComplianceEngine::CHANNELS)], 'text' => ['required', 'string', 'max:5000']]);
        $result = $engine->check($user, ['channel' => (string) $data['channel'], 'text' => (string) $data['text']]);
        $log->record($user, (string) $data['channel'], $result['ok'] ? 'checked' : 'blocked', $result['issues']);

        return response()->json(['data' => $result]);
    }

    /**
     * Fix automatically.
     *
     * Swaps flagged wording for words about the home, or takes it out.
     *
     * @response array{data: array{text: string, ok: bool, issues: list<array{code: 'broker'|'broker_size'|'team_name'|'links'|'claims'|'title'|'fair_housing'|'unclear'|'banned'|'unlicensed', severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>}}
     */
    public function fix(Request $request, #[CurrentUser] User $user, ComplianceEngine $engine, ComplianceLog $log): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(ComplianceEngine::CHANNELS)], 'text' => ['required', 'string', 'max:5000']]);
        $channel = (string) $data['channel'];
        $before = $engine->check($user, ['channel' => $channel, 'text' => (string) $data['text']]);
        $text = $engine->fix($user, (string) $data['text'], $before['issues']);
        $log->record($user, $channel, 'fixed', $before['issues']);

        return response()->json(['data' => ['text' => $text, ...$engine->check($user, ['channel' => $channel, 'text' => $text])]]);
    }
}
