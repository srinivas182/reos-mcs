<?php

declare(strict_types=1);

namespace App\Http\Controllers\Licence;

use App\Http\Controllers\Controller;
use App\Licences\Journey\LicenceJourneys;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** The licence journey on the web (A10.3a): the tracker and ready to book, the exam-day kit, and entering the result. */
final class LicenceJourneyPageController extends Controller
{
    public function show(#[CurrentUser] User $user, LicenceJourneys $journeys): Response
    {
        return Inertia::render('licence/index', ['journey' => $journeys->show($user)]);
    }

    public function examDay(#[CurrentUser] User $user, LicenceJourneys $journeys): Response
    {
        return Inertia::render('licence/exam-day', ['journey' => $journeys->show($user)]);
    }

    public function result(#[CurrentUser] User $user, LicenceJourneys $journeys): Response
    {
        return Inertia::render('licence/result', ['journey' => $journeys->show($user)]);
    }
}
