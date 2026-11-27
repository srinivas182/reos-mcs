<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Studio\Celebrations\CandidateStudio;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The free candidate studio (B13.3, ADR 0059): study-streak, practice-score and exam-pass posts. */
final class CandidateStudioController extends Controller
{
    /**
     * Moments.
     *
     * What the candidate has done that's worth a post right now, and whether each was made.
     *
     * @response array{data: list<array{milestone: 'exam_pass'|'study_streak'|'practice_score', label: string, made: string|null}>}
     */
    public function index(#[CurrentUser] User $user, ActiveRole $roles, CandidateStudio $studio): JsonResponse
    {
        self::candidate($user, $roles);

        return response()->json(['data' => $studio->moments($user)]);
    }

    /**
     * Make a post.
     *
     * Three captions and three designs for the moment, free, each caption already checked.
     *
     * @response array{data: array{id: string, milestone: 'exam_pass'|'study_streak'|'practice_score', label: string, captions: list<array{tone: 'professional'|'warm'|'short', text: string}>, designs: list<array{layout: string, path: string, url: string}>, checks: list<array{caption: int, ok: bool, issues: list<array{code: string, severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>}>}}
     */
    public function make(string $milestone, #[CurrentUser] User $user, ActiveRole $roles, CandidateStudio $studio): JsonResponse
    {
        self::candidate($user, $roles);

        return response()->json(['data' => $studio->make($user, $milestone)]);
    }

    /**
     * Check a caption.
     *
     * The compliance engine on an edited caption: no service advertising before the license is active.
     *
     * @response array{data: array{ok: bool, issues: list<array{code: string, severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}>}}
     */
    public function check(Request $request, #[CurrentUser] User $user, ActiveRole $roles, CandidateStudio $studio): JsonResponse
    {
        self::candidate($user, $roles);
        $request->validate(['text' => ['required', 'string', 'max:2000']]);

        return response()->json(['data' => $studio->check($user, $request->string('text')->toString())]);
    }

    /**
     * Post it.
     *
     * Posts the chosen caption and design to the candidate's connected accounts, after the same checks as any post.
     *
     * @response array{data: array{id: string, status: string}}
     */
    public function post(Request $request, string $post, #[CurrentUser] User $user, ActiveRole $roles, CandidateStudio $studio): JsonResponse
    {
        self::candidate($user, $roles);
        $request->validate([
            'caption' => ['required', 'integer', 'min:0', 'max:2'], 'design' => ['required', 'integer', 'min:0', 'max:2'], 'text' => ['nullable', 'string', 'max:2000'],
            'targets' => ['required', 'array', 'min:1', 'max:12'], 'targets.*.connection' => ['required', 'integer'], 'targets.*.channel' => ['required', 'string', 'max:20'], 'targets.*.id' => ['required', 'string', 'max:120'],
        ]);
        $targets = [];

        foreach ((array) $request->input('targets', []) as $t) {
            if (is_array($t) && is_numeric($t['connection'] ?? null) && is_string($t['channel'] ?? null) && is_string($t['id'] ?? null)) {
                $targets[] = ['connection' => (int) $t['connection'], 'channel' => $t['channel'], 'id' => $t['id']];
            }
        }
        $row = $studio->post($user, $post, $request->integer('caption'), $request->integer('design'), $request->string('text')->toString(), $targets);

        return response()->json(['data' => ['id' => $row['id'], 'status' => $row['status']]]);
    }

    private static function candidate(User $user, ActiveRole $roles): void
    {
        if ($roles->for($user)?->role !== Role::Candidate) {
            throw new NotFoundHttpException;
        }
    }
}
