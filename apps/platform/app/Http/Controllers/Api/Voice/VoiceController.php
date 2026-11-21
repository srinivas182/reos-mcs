<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Voice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Voice\ParseVoiceRequest;
use App\Models\User;
use App\Voice\VoiceParsing;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Voice capture (B4.3, ADR 0018). */
final class VoiceController extends Controller
{
    /**
     * Understand what was said.
     *
     * Returns to-dos for the confirm card: titles, notes, people's names to
     * match against your contacts, due times in your time zone and whether
     * to remind. Claude reads it when AI is set up and you have credits (one
     * per parse); otherwise the built-in parser does. Nothing said is stored.
     *
     * @response array{engine: 'ai'|'parser', items: list<array{title: string, notes: string|null, people: list<string>, due_at: string|null, due_date: string|null, remind_at: string|null}>, credits: array{used: int, left: int}}
     */
    public function parse(ParseVoiceRequest $request, #[CurrentUser] User $user, VoiceParsing $voice): JsonResponse
    {
        return response()->json($voice->parse($user, $request->string('text')->toString(), $request->language(), $request->saidAt()));
    }
}
