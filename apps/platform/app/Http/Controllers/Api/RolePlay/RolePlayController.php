<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\RolePlay;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolePlay\SayRequest;
use App\Http\Requests\RolePlay\StartRolePlayRequest;
use App\Models\User;
use App\RolePlay\RolePlays;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Role-play and the skills assessment (A14.1a, ADR 0060; agent screen 7). Private to the agent. */
final class RolePlayController extends Controller
{
    /**
     * Role-play.
     *
     * What I can practise (five types, each with personas, and my best score), my recent practices, and my skills assessment.
     *
     * @response array{data: array{types: list<array{type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', personas: list<'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead'>, best: int|null}>, recent: list<array{id: string, type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', persona: 'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead', kind: 'practice'|'assessment', status: 'active'|'scored', score: int|null, created_at: string}>, assessment: array{id: string, done: int, total: int, next: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up'|null, completed: bool, results: list<array{type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', score: int}>, weakest: list<'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up'>, completed_at: string|null}|null}}
     */
    public function index(#[CurrentUser] User $user, RolePlays $plays): JsonResponse
    {
        return response()->json(['data' => $plays->overview($user)]);
    }

    /**
     * Start a role-play.
     *
     * The persona speaks first. Answer by voice or typing; after four answers the persona agrees to the next step or declines, and the practice is scored.
     *
     * @response array{data: array{id: string, type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', persona: 'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead', kind: 'practice'|'assessment', round: int|null, status: 'active'|'scored', turns: list<array{role: 'persona'|'agent', text: string}>, turns_left: int, score: int|null, scores: array{structure: int, questions: int, objections: int, clarity: int, ask: int}|null, fixes: list<array{dimension: 'structure'|'questions'|'objections'|'clarity'|'ask', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}>, outcome: 'won'|'lost'|null, engine: 'claude'|'script', created_at: string}}
     */
    public function start(StartRolePlayRequest $request, #[CurrentUser] User $user, RolePlays $plays): JsonResponse
    {
        $play = $plays->start($user, $request->string('type')->toString(), $request->string('persona')->toString());

        return response()->json(['data' => $plays->view($play)]);
    }

    /**
     * Start the skills assessment.
     *
     * Five short rounds, one per type (two answers each). Starts the assessment or returns its next round; a finished assessment starts over.
     *
     * @response array{data: array{id: string, type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', persona: 'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead', kind: 'practice'|'assessment', round: int|null, status: 'active'|'scored', turns: list<array{role: 'persona'|'agent', text: string}>, turns_left: int, score: int|null, scores: array{structure: int, questions: int, objections: int, clarity: int, ask: int}|null, fixes: list<array{dimension: 'structure'|'questions'|'objections'|'clarity'|'ask', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}>, outcome: 'won'|'lost'|null, engine: 'claude'|'script', created_at: string}}
     */
    public function assess(#[CurrentUser] User $user, RolePlays $plays): JsonResponse
    {
        return response()->json(['data' => $plays->view($plays->assess($user))]);
    }

    /**
     * A role-play.
     *
     * @response array{data: array{id: string, type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', persona: 'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead', kind: 'practice'|'assessment', round: int|null, status: 'active'|'scored', turns: list<array{role: 'persona'|'agent', text: string}>, turns_left: int, score: int|null, scores: array{structure: int, questions: int, objections: int, clarity: int, ask: int}|null, fixes: list<array{dimension: 'structure'|'questions'|'objections'|'clarity'|'ask', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}>, outcome: 'won'|'lost'|null, engine: 'claude'|'script', created_at: string}}
     */
    public function show(string $rolePlay, #[CurrentUser] User $user, RolePlays $plays): JsonResponse
    {
        return response()->json(['data' => $plays->show($user, $rolePlay)]);
    }

    /**
     * Answer.
     *
     * What I said (spoken words or typing); the persona replies. After the last answer it's scored: a score out of 100 and three fixes.
     *
     * @response array{data: array{id: string, type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', persona: 'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead', kind: 'practice'|'assessment', round: int|null, status: 'active'|'scored', turns: list<array{role: 'persona'|'agent', text: string}>, turns_left: int, score: int|null, scores: array{structure: int, questions: int, objections: int, clarity: int, ask: int}|null, fixes: list<array{dimension: 'structure'|'questions'|'objections'|'clarity'|'ask', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}>, outcome: 'won'|'lost'|null, engine: 'claude'|'script', created_at: string}}
     */
    public function say(SayRequest $request, string $rolePlay, #[CurrentUser] User $user, RolePlays $plays): JsonResponse
    {
        return response()->json(['data' => $plays->say($user, $rolePlay, $request->string('text')->toString())]);
    }

    /**
     * Finish and score.
     *
     * Ends the practice early and scores what I said: a score out of 100 on five parts and three fixes, each with a line to try and a lesson.
     *
     * @response array{data: array{id: string, type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', persona: 'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead', kind: 'practice'|'assessment', round: int|null, status: 'active'|'scored', turns: list<array{role: 'persona'|'agent', text: string}>, turns_left: int, score: int|null, scores: array{structure: int, questions: int, objections: int, clarity: int, ask: int}|null, fixes: list<array{dimension: 'structure'|'questions'|'objections'|'clarity'|'ask', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}>, outcome: 'won'|'lost'|null, engine: 'claude'|'script', created_at: string}}
     */
    public function finish(string $rolePlay, #[CurrentUser] User $user, RolePlays $plays): JsonResponse
    {
        return response()->json(['data' => $plays->finish($user, $rolePlay)]);
    }
}
