<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Practice\PracticeAccess;
use App\Practice\Readiness;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Readiness for the phone (candidate screens 54 and 44, A9.2). */
final class ReadinessController extends Controller
{
    /**
     * The Readiness map.
     *
     * The predicted pass chance for each exam portion against the 90%
     * booking line; what booking still needs (both portions at the line and
     * two recent simulations passed) and where to book; every topic weakest
     * first with its share right, answers, trend over the last week (from 10
     * answers) and exam portion; and how many points of pass chance fixing
     * the weakest topic would add. Kept nightly and after every test.
     *
     * @response array{data: array{percent: int|null, portions: array{national: int|null, state: int|null}, line: int, booking: array{ready: bool, national: bool, state: bool, simulations: int, needed: int, url: string|null}, topics: list<array{topic: string, portion: string, weight: int, percent: int|null, answers: int, shown: bool, trend: int|null, weak: bool}>, gain: array{topic: string, portion: string, points: int}|null, computed_on: string|null}}
     */
    public function show(#[CurrentUser] User $user, PracticeAccess $access, Readiness $readiness): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $readiness->map($user)]);
    }
}
