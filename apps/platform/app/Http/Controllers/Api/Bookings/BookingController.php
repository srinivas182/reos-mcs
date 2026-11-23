<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\BookSessionRequest;
use App\Http\Requests\Bookings\SessionOfferRequest;
use App\Models\SessionBooking;
use App\Models\SessionOffer;
use App\Models\User;
use App\Sessions\SessionBookings;
use App\Video\VideoSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Booking 1-on-1 sessions (A10.2a, ADR 0040; candidate screen 18, tutor
 * rate; instructor i14 and i16): instructors' hours and prices, free slots,
 * booking (included, or paid through checkout), cancelling, rating and
 * follow-up tasks.
 */
final class BookingController extends Controller
{
    /**
     * Instructors and mentors to book.
     *
     * Each with rating, languages, topics, prices (cents; 0 is included) and next free hour.
     *
     * @response array{data: list<array{id: int, user_id: int, name: string, school: string|null, bio: string|null, languages: list<string>, topics: list<string>, price_30: int, price_60: int, rating: float|null, ratings: int, next_slot: string|null}>}
     */
    public function instructors(SessionBookings $bookings): JsonResponse
    {
        return response()->json(['data' => $bookings->offers()]);
    }

    /**
     * One instructor and their free slots.
     *
     * Slots for the next 14 days, 30 and 60 minutes (UTC).
     *
     * @response array{data: array{id: int, user_id: int, name: string, school: string|null, bio: string|null, languages: list<string>, topics: list<string>, price_30: int, price_60: int, rating: float|null, ratings: int, next_slot: string|null}, slots_30: list<string>, slots_60: list<string>}
     */
    public function instructor(int $user, SessionBookings $bookings): JsonResponse
    {
        $offer = SessionOffer::query()->where('user_id', $user)->where('active', true)->firstOrFail();

        return response()->json(['data' => $bookings->offers($user)[0], 'slots_30' => $bookings->slots($offer, 30), 'slots_60' => $bookings->slots($offer, 60)]);
    }

    /**
     * Book a session.
     *
     * Included sessions are confirmed at once. Paid ones are held for 30 minutes: pay at `checkout_url`.
     *
     * @status 201
     *
     * @response array{data: array{uuid: string, as: 'student'|'instructor', instructor: string, student: string, starts_at: string, ends_at: string, minutes: int, topic: string, status: 'held'|'confirmed'|'cancelled'|'refunded'|'completed'|'no_show', price: int, session: string|null, can_cancel: bool, refund_on_cancel: bool, can_rate: bool, rating: int|null, tasks_sent: bool, checkout_url: string|null}, checkout_url: string|null}
     */
    public function store(BookSessionRequest $request, #[CurrentUser] User $user, SessionBookings $bookings): JsonResponse
    {
        $offer = SessionOffer::query()->where('user_id', $request->integer('instructor'))->firstOrFail();
        $booked = $bookings->book($user, $offer, $request->startsAt(), $request->integer('minutes'), $request->string('topic')->toString());

        return response()->json(['data' => $bookings->row($booked['booking']->refresh(), $user), 'checkout_url' => $booked['checkout_url']], 201);
    }

    /**
     * My bookings.
     *
     * As a student and as an instructor, the last 30 days and upcoming.
     *
     * @response array{data: list<array{uuid: string, as: 'student'|'instructor', instructor: string, student: string, starts_at: string, ends_at: string, minutes: int, topic: string, status: 'held'|'confirmed'|'cancelled'|'refunded'|'completed'|'no_show', price: int, session: string|null, can_cancel: bool, refund_on_cancel: bool, can_rate: bool, rating: int|null, tasks_sent: bool, checkout_url: string|null}>}
     */
    public function index(#[CurrentUser] User $user, SessionBookings $bookings): JsonResponse
    {
        return response()->json(['data' => $bookings->for($user)]);
    }

    /**
     * Cancel a booking.
     *
     * The student until 24 hours before (refunded) or later (not refunded); the instructor any time (refunded).
     *
     * @response array{data: array{uuid: string, as: 'student'|'instructor', instructor: string, student: string, starts_at: string, ends_at: string, minutes: int, topic: string, status: 'held'|'confirmed'|'cancelled'|'refunded'|'completed'|'no_show', price: int, session: string|null, can_cancel: bool, refund_on_cancel: bool, can_rate: bool, rating: int|null, tasks_sent: bool, checkout_url: string|null}}
     */
    public function cancel(SessionBooking $booking, #[CurrentUser] User $user, SessionBookings $bookings): JsonResponse
    {
        $this->mine($booking, $user);

        return response()->json(['data' => $bookings->row($bookings->cancel($user, $booking), $user)]);
    }

    /**
     * Rate a session.
     *
     * Once, after it ends; shown on the instructor's profile after 24 hours.
     *
     * @response array{data: array{uuid: string, as: 'student'|'instructor', instructor: string, student: string, starts_at: string, ends_at: string, minutes: int, topic: string, status: 'held'|'confirmed'|'cancelled'|'refunded'|'completed'|'no_show', price: int, session: string|null, can_cancel: bool, refund_on_cancel: bool, can_rate: bool, rating: int|null, tasks_sent: bool, checkout_url: string|null}}
     */
    public function rate(Request $request, SessionBooking $booking, #[CurrentUser] User $user, SessionBookings $bookings): JsonResponse
    {
        $this->mine($booking, $user);
        $data = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'tags' => ['sometimes', 'array', 'max:5'],
            'tags.*' => ['in:'.implode(',', SessionBooking::TAGS)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $tags = array_values(array_filter(is_array($data['tags'] ?? null) ? $data['tags'] : [], is_string(...)));

        return response()->json(['data' => $bookings->row($bookings->rate($user, $booking, (int) $data['stars'], $tags, is_string($data['comment'] ?? null) ? $data['comment'] : null), $user)]);
    }

    /**
     * Suggested follow-up tasks.
     *
     * Lines in the session's shared notes that read like actions, for the instructor to send.
     *
     * @response array{data: list<string>}
     */
    public function suggestedTasks(SessionBooking $booking, #[CurrentUser] User $user): JsonResponse
    {
        abort_unless($booking->instructor_id === $user->id, 404);

        return response()->json(['data' => SessionBookings::suggestedTasks($booking->session?->notes)]);
    }

    /**
     * Send follow-up tasks.
     *
     * Each becomes a to-do for the student, on its date.
     *
     * @response array{data: array{sent: int}}
     */
    public function sendTasks(Request $request, SessionBooking $booking, #[CurrentUser] User $user, SessionBookings $bookings): JsonResponse
    {
        abort_unless($booking->instructor_id === $user->id, 404);
        $data = $request->validate([
            'tasks' => ['required', 'array', 'min:1', 'max:10'],
            'tasks.*.title' => ['required', 'string', 'min:2', 'max:200'],
            'tasks.*.due_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $tasks = [];
        foreach (is_array($data['tasks']) ? $data['tasks'] : [] as $task) {
            if (is_array($task) && is_string($task['title'] ?? null)) {
                $tasks[] = ['title' => $task['title'], 'due_date' => is_string($task['due_date'] ?? null) ? $task['due_date'] : null];
            }
        }

        return response()->json(['data' => ['sent' => $bookings->sendTasks($user, $booking, $tasks)]]);
    }

    /**
     * My hours and prices.
     *
     * The instructor's or mentor's offer, or null before they set one.
     *
     * @response array{data: array{bio: string|null, languages: list<'en'|'es'>, topics: list<string>, hours: array<string, list<list<string>>>, timezone: string, price_30_cents: int, price_60_cents: int, active: bool}|null}
     */
    public function offer(#[CurrentUser] User $user, VideoSessions $sessions): JsonResponse
    {
        abort_unless($sessions->canHost($user), 403);

        return response()->json(['data' => self::offerRow(SessionOffer::query()->where('user_id', $user->id)->first())]);
    }

    /**
     * Save my hours and prices.
     *
     * @response array{data: array{bio: string|null, languages: list<'en'|'es'>, topics: list<string>, hours: array<string, list<list<string>>>, timezone: string, price_30_cents: int, price_60_cents: int, active: bool}|null}
     */
    public function saveOffer(SessionOfferRequest $request, #[CurrentUser] User $user, VideoSessions $sessions, SessionBookings $bookings): JsonResponse
    {
        abort_unless($sessions->canHost($user), 403);

        return response()->json(['data' => self::offerRow($bookings->saveOffer($user, $request->values()))]);
    }

    /** @return array{bio: string|null, languages: list<'en'|'es'>, topics: list<string>, hours: array<string, list<list<string>>>, timezone: string, price_30_cents: int, price_60_cents: int, active: bool}|null */
    public static function offerRow(?SessionOffer $offer): ?array
    {
        return $offer === null ? null : [
            'bio' => $offer->bio, 'languages' => $offer->languages, 'topics' => $offer->topics, 'hours' => $offer->hours, 'timezone' => $offer->timezone,
            'price_30_cents' => $offer->price_30_cents, 'price_60_cents' => $offer->price_60_cents, 'active' => $offer->active,
        ];
    }

    private function mine(SessionBooking $booking, User $user): void
    {
        abort_unless($booking->student_id === $user->id || $booking->instructor_id === $user->id, 404);
    }
}
