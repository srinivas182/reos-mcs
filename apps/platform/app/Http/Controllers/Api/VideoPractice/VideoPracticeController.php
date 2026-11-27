<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\VideoPractice;

use App\Http\Controllers\Controller;
use App\Http\Requests\VideoPractice\ReviewVideoRequest;
use App\Models\User;
use App\VideoPractice\VideoPractices;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Video practice with an AI review (A14.1b, ADR 0061). The video stays on the device; private to the agent. */
final class VideoPracticeController extends Controller
{
    /**
     * Video practice.
     *
     * What I can record (a listing pitch or a video intro), the longest recording, and my recent reviews.
     *
     * @response array{data: array{kinds: list<'listing_pitch'|'video_intro'>, max_seconds: int, recent: list<array{id: string, kind: 'listing_pitch'|'video_intro', score: int, wpm: int, duration_seconds: int, created_at: string}>}}
     */
    public function index(#[CurrentUser] User $user, VideoPractices $practices): JsonResponse
    {
        return response()->json(['data' => $practices->overview($user)]);
    }

    /**
     * Review a recording.
     *
     * Send the words (recognised on the device, corrected if needed) and the length; the video itself is never sent. Returns the pace, filler words, a score out of 100, three fixes, and what the compliance engine would flag if I posted it.
     *
     * @response array{data: array{id: string, kind: 'listing_pitch'|'video_intro', transcript: string, duration_seconds: int, words: int, wpm: int, pace: 'slow'|'good'|'fast', fillers: list<array{word: string, count: int}>, score: int, scores: array{pace: int, fillers: int, opening: int, proof: int, close: int}, fixes: list<array{dimension: 'pace'|'fillers'|'opening'|'proof'|'close', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}>, compliance: list<array{code: string, phrase: string|null}>, engine: 'claude'|'script', created_at: string}}
     */
    public function review(ReviewVideoRequest $request, #[CurrentUser] User $user, VideoPractices $practices): JsonResponse
    {
        return response()->json(['data' => $practices->review($user, $request->kind(), $request->string('transcript')->toString(), $request->integer('duration_seconds'))]);
    }

    /**
     * A review.
     *
     * @response array{data: array{id: string, kind: 'listing_pitch'|'video_intro', transcript: string, duration_seconds: int, words: int, wpm: int, pace: 'slow'|'good'|'fast', fillers: list<array{word: string, count: int}>, score: int, scores: array{pace: int, fillers: int, opening: int, proof: int, close: int}, fixes: list<array{dimension: 'pace'|'fillers'|'opening'|'proof'|'close', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}>, compliance: list<array{code: string, phrase: string|null}>, engine: 'claude'|'script', created_at: string}}
     */
    public function show(string $videoPractice, #[CurrentUser] User $user, VideoPractices $practices): JsonResponse
    {
        return response()->json(['data' => $practices->show($user, $videoPractice)]);
    }
}
