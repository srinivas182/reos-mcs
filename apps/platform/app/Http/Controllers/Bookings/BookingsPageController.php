<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Api\Bookings\BookingController;
use App\Http\Controllers\Controller;
use App\Models\SessionOffer;
use App\Models\User;
use App\Sessions\SessionBookings;
use App\Video\VideoSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Booking 1-on-1 sessions on the web (A10.2a): instructors, one instructor's slots, and an instructor's hours. */
final class BookingsPageController extends Controller
{
    public function index(#[CurrentUser] User $user, SessionBookings $bookings, VideoSessions $sessions): Response
    {
        return Inertia::render('bookings/index', [
            'instructors' => $bookings->offers(),
            'bookings' => $bookings->for($user),
            'can_teach' => $sessions->canHost($user),
        ]);
    }

    public function instructor(int $user, SessionBookings $bookings): Response
    {
        $offer = SessionOffer::query()->where('user_id', $user)->where('active', true)->firstOrFail();

        return Inertia::render('bookings/instructor', [
            'instructor' => $bookings->offers($user)[0],
            'slots' => ['30' => $bookings->slots($offer, 30), '60' => $bookings->slots($offer, 60)],
        ]);
    }

    public function hours(#[CurrentUser] User $user, VideoSessions $sessions): Response
    {
        abort_unless($sessions->canHost($user), 403);

        return Inertia::render('bookings/hours', ['offer' => BookingController::offerRow(SessionOffer::query()->where('user_id', $user->id)->first())]);
    }
}
