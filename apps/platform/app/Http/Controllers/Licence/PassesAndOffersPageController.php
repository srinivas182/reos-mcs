<?php

declare(strict_types=1);

namespace App\Http\Controllers\Licence;

use App\Http\Controllers\Controller;
use App\Licences\Journey\BrokerOffers;
use App\Licences\Journey\PassAnnouncements;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Passes (f06) and broker offers (17) on the web (A10.3b). */
final class PassesAndOffersPageController extends Controller
{
    public function passes(#[CurrentUser] User $user, PassAnnouncements $news): Response
    {
        return Inertia::render('licence/passes', ['feed' => $news->feed($user)]);
    }

    public function offers(#[CurrentUser] User $user, BrokerOffers $offers): Response
    {
        return Inertia::render('licence/offers', ['offers' => $offers->forCandidate($user)]);
    }
}
