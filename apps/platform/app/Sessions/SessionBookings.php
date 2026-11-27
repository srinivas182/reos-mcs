<?php

declare(strict_types=1);

namespace App\Sessions;

use App\Authorization\Role;
use App\Billing\Buyer;
use App\Billing\Checkout;
use App\Billing\Ledger\MoneyPostings;
use App\Billing\Ledger\Points;
use App\Billing\OrderStatus;
use App\Billing\Payouts\PayeeRole;
use App\Billing\Payouts\RevenueSplit;
use App\Billing\PriceInterval;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Billing\Refunds;
use App\Enums\StateStatus;
use App\Mentoring\MentorDirectory;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductSplit;
use App\Models\SessionBooking;
use App\Models\SessionOffer;
use App\Models\State;
use App\Models\User;
use App\Models\VideoSessionParticipant;
use App\Notifications\SessionRefunded;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Services\States\PersonStates;
use App\Services\Todos\Todos;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Booking 1-on-1 sessions (A10.2a, ADR 0040; candidate screens 18, tutor
 * live and tutor rate; instructor i14 and i16).
 *
 * - **Hours and prices:** each instructor or mentor sets weekly hours (in
 *   their time zone), 30- and 60-minute prices (0 means included) and what
 *   they help with. A paid length is a session product their school sells,
 *   with the instructor's share paid out as for courses (decision 3).
 * - **Book:** a free slot in the next 14 days, at least 2 hours ahead, on
 *   the half hour. Included sessions are confirmed at once; paid ones are
 *   held for 30 minutes while the student pays through checkout (decision 2).
 * - **Rules:** free cancellation until 24 hours before (a full refund); the
 *   instructor may cancel any time (a full refund); if the instructor
 *   hasn't joined 10 minutes after the start, the student is refunded
 *   automatically. The session ends at the booked time (B6.3).
 * - **After:** the student rates it (shown on the profile after 24 hours);
 *   the instructor sends tasks from the notes to the student's to-dos.
 *
 * @phpstan-type OfferRow array{id: int, user_id: int, name: string, school: string|null, bio: string|null, languages: list<string>, topics: list<string>, price_30: int, price_60: int, rating: float|null, ratings: int, next_slot: string|null}
 * @phpstan-type BookingRow array{uuid: string, as: string, instructor: string, student: string, starts_at: string, ends_at: string, minutes: int, topic: string, status: string, price: int, session: string|null, can_cancel: bool, refund_on_cancel: bool, can_rate: bool, rating: int|null, tasks_sent: bool, checkout_url: string|null, paid_with: string, points: int|null}
 */
final readonly class SessionBookings
{
    public const DAYS_AHEAD = 14;

    public const LEAD_HOURS = 2;

    public const HOLD_MINUTES = 30;

    public const FREE_CANCEL_HOURS = 24;

    public const NO_SHOW_MINUTES = 10;

    public const RATING_DELAY_HOURS = 24;

    /** The gateway name on orders paid with points (A13.1a). */
    public const POINTS = 'points';

    public function __construct(private VideoSessions $sessions, private Refunds $refunds, private Todos $todos, private PersonStates $states, private AuditLogger $audit, private MentorDirectory $mentors, private Points $points, private RevenueSplit $split, private MoneyPostings $money) {}

    /**
     * The instructor's hours, prices and topics; paid lengths become session products.
     *
     * @param  array{bio: string|null, languages: list<'en'|'es'>, topics: list<string>, hours: array<string, list<array{0: string, 1: string}>>, timezone: string, price_30_cents: int, price_60_cents: int, active: bool}  $values
     */
    public function saveOffer(User $instructor, array $values): SessionOffer
    {
        $school = $this->sessions->hostOrganisation($instructor) ?? throw ValidationException::withMessages(['offer' => __('sessions.errors.notHost')]);
        $limits = self::limitsOf($school);

        // The school's limit (decision 3): no more than it allows per length.
        foreach (SessionOffer::LENGTHS as $minutes) {
            $max = $limits["max_{$minutes}_cents"];

            if ($max !== null && $values["price_{$minutes}_cents"] > $max) {
                throw ValidationException::withMessages(["price_{$minutes}_cents" => __('bookings.hours.overLimit', ['price' => '$'.number_format($max / 100, $max % 100 === 0 ? 0 : 2)])]);
            }
        }

        // A brokerage mentor's prices are set by the brokerage (A13.1a, broker screen 22).
        $brokerage = $this->mentors->settingsFor($instructor);

        if ($brokerage !== null && $brokerage->exists) {
            $values['price_30_cents'] = $brokerage->price_30_cents;
            $values['price_60_cents'] = $brokerage->price_60_cents;
        }

        return DB::transaction(function () use ($instructor, $values, $school): SessionOffer {
            $offer = SessionOffer::query()->firstOrNew(['user_id' => $instructor->id]);
            $offer->fill($values);

            $this->products($instructor, $offer, $school);
            $offer->save();

            return $offer;
        });
    }

    /**
     * The brokerage sets a mentor's prices and topics (A13.1a): the mentor's
     * offer follows, with hours left as the mentor set them.
     *
     * @param  list<string>  $topics
     */
    public function setPrices(User $mentor, int $price30, int $price60, array $topics): SessionOffer
    {
        $organisation = $this->sessions->hostOrganisation($mentor) ?? throw ValidationException::withMessages(['offer' => __('sessions.errors.notHost')]);

        return DB::transaction(function () use ($mentor, $price30, $price60, $topics, $organisation): SessionOffer {
            $offer = SessionOffer::query()->firstOrNew(['user_id' => $mentor->id], ['bio' => null, 'languages' => ['en'], 'hours' => [], 'timezone' => 'America/Chicago', 'active' => true, 'topics' => []]);
            $offer->fill(['price_30_cents' => $price30, 'price_60_cents' => $price60, 'topics' => $topics === [] ? $offer->topics : $topics]);
            $this->products($mentor, $offer, $organisation);
            $offer->save();

            return $offer;
        });
    }

    /** Paid lengths become session products sold by the school or brokerage, the host taking their share. */
    private function products(User $instructor, SessionOffer $offer, Organisation $school): void
    {
        $role = $this->mentors->settingsFor($instructor) !== null ? PayeeRole::Mentor : PayeeRole::Instructor;

        foreach (SessionOffer::LENGTHS as $minutes) {
            $cents = $offer->price($minutes);
            $product = Product::query()->firstOrNew(['key' => "session-{$instructor->id}-{$minutes}"]);
            $product->forceFill([
                'name' => mb_substr(__('sessions.booking.productName', ['name' => (string) $instructor->name, 'minutes' => (string) $minutes]), 0, 120),
                'kind' => ProductKind::Session, 'buyer' => Buyer::Candidate, 'seller_organisation_id' => $school->id,
                'status' => $cents > 0 && $offer->active ? ProductStatus::Live : ProductStatus::Retired,
            ])->save();
            ProductSplit::query()->updateOrCreate(['product_id' => $product->id, 'payee_user_id' => $instructor->id], ['role' => $role, 'percent' => 100]);

            foreach (State::query()->where('status', StateStatus::Live)->pluck('code') as $code) {
                Price::query()->updateOrCreate(
                    ['product_id' => $product->id, 'state_code' => $code, 'interval' => PriceInterval::Once],
                    ['amount_cents' => max(0, $cents), 'currency' => 'usd', 'starts_at' => $offer->exists ? Price::query()->where('product_id', $product->id)->where('state_code', $code)->value('starts_at') ?? now() : now(), 'ends_at' => null],
                );
            }

            $offer->forceFill([$minutes === 30 ? 'product_30_id' : 'product_60_id' => $product->id]);
        }
    }

    /**
     * The most a school's instructors may charge (decision 3); null is no limit.
     *
     * @return array{max_30_cents: int|null, max_60_cents: int|null}
     */
    public static function limitsOf(?Organisation $school): array
    {
        return ['max_30_cents' => $school?->session_max_30_cents, 'max_60_cents' => $school?->session_max_60_cents];
    }

    /** @return array{max_30_cents: int|null, max_60_cents: int|null} */
    public function limitsFor(User $instructor): array
    {
        return self::limitsOf($this->sessions->hostOrganisation($instructor));
    }

    /**
     * The school sets its limits (screen 52). Prices above them come down
     * to the limit at once, so no student is charged more.
     */
    public function setLimits(Organisation $school, ?int $max30, ?int $max60, User $actor): int
    {
        $school->forceFill(['session_max_30_cents' => $max30, 'session_max_60_cents' => $max60])->save();
        $lowered = 0;
        $instructors = Product::query()->where('kind', ProductKind::Session)->where('seller_organisation_id', $school->id)
            ->join('product_splits', 'product_splits.product_id', '=', 'products.id')->distinct()->pluck('product_splits.payee_user_id');

        foreach (SessionOffer::query()->whereIn('user_id', $instructors)->with('user')->get() as $offer) {
            $price30 = $max30 === null ? $offer->price_30_cents : min($offer->price_30_cents, $max30);
            $price60 = $max60 === null ? $offer->price_60_cents : min($offer->price_60_cents, $max60);

            if ($price30 !== $offer->price_30_cents || $price60 !== $offer->price_60_cents) {
                $this->saveOffer($offer->user, [
                    'bio' => $offer->bio, 'languages' => $offer->languages, 'topics' => $offer->topics, 'hours' => $offer->hours,
                    'timezone' => $offer->timezone, 'price_30_cents' => $price30, 'price_60_cents' => $price60, 'active' => $offer->active,
                ]);
                $lowered++;
            }
        }

        $this->audit->record('school.session_limits_changed', 'organisation', (string) $school->id, [
            'max_30_cents' => $max30, 'max_60_cents' => $max60, 'offers_lowered' => $lowered,
        ], $actor, $school->id);

        return $lowered;
    }

    /**
     * Free start times for a length, in the next 14 days.
     *
     * @return list<string> ISO 8601, UTC
     */
    public function slots(SessionOffer $offer, int $minutes): array
    {
        $zone = $offer->timezone;
        $now = CarbonImmutable::now();
        $earliest = $now->addHours(self::LEAD_HOURS);
        $taken = SessionBooking::query()->where('instructor_id', $offer->user_id)->whereIn('status', SessionBooking::BUSY)
            ->where('ends_at', '>', $now)->get(['starts_at', 'ends_at']);
        $slots = [];

        for ($d = 0; $d <= self::DAYS_AHEAD; $d++) {
            $day = $now->setTimezone($zone)->startOfDay()->addDays($d);
            $key = strtolower($day->format('D'));

            foreach ($offer->hours[$key] ?? [] as [$from, $to]) {
                $start = CarbonImmutable::parse($day->toDateString().' '.$from, $zone);
                $end = CarbonImmutable::parse($day->toDateString().' '.$to, $zone);

                for ($at = $start; $at->addMinutes($minutes)->lessThanOrEqualTo($end); $at = $at->addMinutes(30)) {
                    $until = $at->addMinutes($minutes);
                    if ($at->lessThan($earliest)) {
                        continue;
                    }
                    $clash = $taken->contains(static fn (SessionBooking $b): bool => $b->starts_at->lessThan($until) && $b->ends_at->greaterThan($at));
                    if (! $clash && ! $this->sessions->busy($offer->user, $at, $until)) {
                        $slots[] = $at->utc()->toIso8601ZuluString();
                    }
                }
            }
        }

        return $slots;
    }

    /**
     * Book a slot. Included sessions are confirmed now; paid ones are held
     * while the student pays (`checkout_url`).
     *
     * @return array{booking: SessionBooking, checkout_url: string|null}
     */
    public function book(User $student, SessionOffer $offer, CarbonImmutable $starts, int $minutes, string $topic, string $payWith = 'card'): array
    {
        if (! $offer->active || $offer->user_id === $student->id || ! in_array($minutes, SessionOffer::LENGTHS, true)) {
            throw ValidationException::withMessages(['starts_at' => __('sessions.booking.errors.unavailable')]);
        }

        // Brokerage mentors (A13.1a): "who can book", the assigned mentor free, points if the brokerage accepts them.
        $mentor = $this->mentors->settingsFor($offer->user);

        if ($mentor !== null && ! $this->mentors->canBook($student, $offer->user)) {
            throw ValidationException::withMessages(['instructor' => __('mentoring.errors.assignedOnly')]);
        }

        $free = $mentor !== null && $this->mentors->isAssigned($student, $offer->user_id);

        if ($payWith === 'points' && ! $free && ($mentor === null || ! $mentor->points)) {
            throw ValidationException::withMessages(['pay_with' => __('mentoring.errors.noPoints')]);
        }

        $booking = DB::transaction(function () use ($student, $offer, $starts, $minutes, $topic, $free, $payWith): SessionBooking {
            // One at a time: lock the instructor's bookings while checking the slot.
            SessionBooking::query()->where('instructor_id', $offer->user_id)->lockForUpdate()->get(['id']);

            if (! in_array($starts->utc()->toIso8601ZuluString(), $this->slots($offer, $minutes), true)) {
                throw ValidationException::withMessages(['starts_at' => __('sessions.booking.errors.taken')]);
            }

            $price = $free ? 0 : $offer->price($minutes);
            $points = $price > 0 && $payWith === 'points';

            $booking = SessionBooking::query()->create([
                'uuid' => (string) Str::uuid(), 'student_id' => $student->id, 'instructor_id' => $offer->user_id, 'starts_at' => $starts->utc(), 'ends_at' => $starts->utc()->addMinutes($minutes),
                'minutes' => $minutes, 'topic' => mb_substr(trim($topic), 0, 200), 'status' => 'held', 'price_cents' => $price,
                'held_until' => $price > 0 && ! $points ? CarbonImmutable::now()->addMinutes(self::HOLD_MINUTES) : null,
                'paid_with' => $free ? 'free' : ($points ? 'points' : 'card'), 'points' => $points ? $price : null,
            ]);

            if ($points) {
                $this->payWithPoints($booking, $offer, $student);
            }

            return $booking;
        });

        if ($booking->price_cents === 0 || $booking->paid_with === 'points') {
            return ['booking' => $this->confirm($booking), 'checkout_url' => null];
        }

        return ['booking' => $booking, 'checkout_url' => $this->checkoutUrl($booking, $offer, $student)];
    }

    /** Where the student pays for a held booking: checkout for the session's price in their state. */
    public function checkoutUrl(SessionBooking $booking, SessionOffer $offer, User $student): ?string
    {
        $product = $offer->productId($booking->minutes);
        $state = $this->states->current($student);
        $price = Price::query()->where('product_id', $product)->where('state_code', $state)->first()
            ?? Price::query()->where('product_id', $product)->orderBy('id')->first();

        return $price === null ? null : route('checkout.show', $price).'?booking='.$booking->uuid;
    }

    /** The order for a held booking was paid: confirm it. Safe to call twice. */
    public function paid(Order $order): void
    {
        $booking = SessionBooking::query()->where('order_id', $order->id)->first();

        if ($booking !== null && $booking->status === 'held') {
            $this->confirm($booking);
        } elseif ($booking !== null && $booking->status === 'cancelled' && $booking->refunded_at === null) {
            // Paid after the hold lapsed and someone else took the slot: give the money back.
            $this->refund($booking, $booking->student, 'Paid after the hold expired');
        }
    }

    public function confirm(SessionBooking $booking): SessionBooking
    {
        $session = $this->sessions->booked($booking->instructor, $booking->student, __('sessions.booking.title', ['name' => (string) $booking->student->name]), $booking->topic, CarbonImmutable::instance($booking->starts_at), $booking->minutes);
        $booking->forceFill(['status' => 'confirmed', 'video_session_id' => $session->id, 'held_until' => null])->save();

        return $booking;
    }

    /** Cancel: the student until 24 hours before (with a refund) or later (without); the instructor any time (with a refund). */
    public function cancel(User $by, SessionBooking $booking): SessionBooking
    {
        if (! in_array($booking->status, SessionBooking::BUSY, true) || $booking->starts_at->isPast()) {
            throw ValidationException::withMessages(['booking' => __('sessions.booking.errors.cannotCancel')]);
        }

        $byInstructor = $by->id === $booking->instructor_id;
        $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $by->id])->save();

        if ($booking->session !== null) {
            $this->sessions->callOff($booking->session, except: $by->id);
        }

        if ($byInstructor || self::refundOnCancel($booking)) {
            $this->refund($booking, $by, $byInstructor ? 'Cancelled by the instructor' : 'Cancelled more than 24 hours ahead');
        }

        return $booking->refresh();
    }

    /** Confirmed sessions the instructor hasn't joined 10 minutes after the start: refunded and called off. */
    public function refundNoShows(): int
    {
        $due = SessionBooking::query()->with(['session', 'student', 'instructor'])->where('status', 'confirmed')
            ->where('starts_at', '<=', now()->subMinutes(self::NO_SHOW_MINUTES))->where('ends_at', '>', now()->subHours(2))->get();
        $count = 0;

        foreach ($due as $booking) {
            $joined = $booking->session === null ? false : $booking->session->participants()->where('role', VideoSessionParticipant::HOST)->whereNotNull('first_joined_at')->exists();

            if ($joined) {
                continue;
            }

            $booking->forceFill(['status' => 'no_show'])->save();
            if ($booking->session !== null) {
                $this->sessions->callOff($booking->session);
            }
            $this->refund($booking, $booking->student, 'The instructor did not join');
            $booking->student->notify(new SessionRefunded($booking->uuid, (string) $booking->instructor->name, $booking->price_cents > 0));
            $count++;
        }

        return $count;
    }

    /** Held bookings whose payment never came: the slot is free again. */
    public function releaseHolds(): int
    {
        return SessionBooking::query()->where('status', 'held')->whereNotNull('held_until')->where('held_until', '<', now())
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    /** Ended sessions are complete (for rating and tasks). */
    public function complete(): int
    {
        return SessionBooking::query()->where('status', 'confirmed')->where('ends_at', '<=', now())
            ->whereHas('session', static fn ($q) => $q->whereHas('participants', static fn ($p) => $p->where('role', VideoSessionParticipant::HOST)->whereNotNull('first_joined_at')))
            ->update(['status' => 'completed']);
    }

    /** @param  list<string>  $tags */
    public function rate(User $student, SessionBooking $booking, int $stars, array $tags, ?string $comment): SessionBooking
    {
        if ($booking->student_id !== $student->id || $booking->status !== 'completed' || $booking->rated_at !== null) {
            throw ValidationException::withMessages(['rating' => __('sessions.booking.errors.cannotRate')]);
        }

        $booking->forceFill(['rating' => $stars, 'rating_tags' => array_values(array_intersect($tags, SessionBooking::TAGS)), 'rating_comment' => $comment, 'rated_at' => now()])->save();

        return $booking;
    }

    /**
     * The instructor's follow-up tasks become the student's to-dos (screen i16).
     *
     * @param  list<array{title: string, due_date: string|null}>  $tasks
     */
    public function sendTasks(User $instructor, SessionBooking $booking, array $tasks): int
    {
        if ($booking->instructor_id !== $instructor->id || ! in_array($booking->status, ['confirmed', 'completed'], true) || $booking->starts_at->isFuture()) {
            throw ValidationException::withMessages(['tasks' => __('sessions.booking.errors.cannotSendTasks')]);
        }

        foreach ($tasks as $task) {
            $this->todos->create($booking->student, [
                'title' => mb_substr($task['title'], 0, 200), 'due_date' => $task['due_date'], 'related_type' => 'session', 'related_id' => $booking->uuid,
                'related_label' => (string) $booking->instructor->name, 'source' => 'session',
            ], null);
        }

        $booking->forceFill(['tasks_sent_at' => now()])->save();

        return count($tasks);
    }

    /**
     * Tasks suggested from the session's shared notes: each line that reads
     * like an action ("- Review proration", "2. Redo the math drill").
     *
     * @return list<string>
     */
    public static function suggestedTasks(?string $notes): array
    {
        $tasks = [];
        foreach (preg_split('/\R/u', (string) $notes) ?: [] as $line) {
            if (preg_match('/^\s*(?:[-*•]|\d+[.)])\s+(.{3,})$/u', $line, $m) === 1) {
                $tasks[] = mb_substr(trim($m[1]), 0, 200);
            }
        }

        return array_slice($tasks, 0, 10);
    }

    public static function refundOnCancel(SessionBooking $booking): bool
    {
        return $booking->starts_at->greaterThanOrEqualTo(now()->addHours(self::FREE_CANCEL_HOURS));
    }

    /**
     * Pays a mentor session with points (A13.1a): the points are spent and
     * an order is written as if paid, at 1 point = 1 cent, so the mentor's
     * share reaches their earnings and payouts like a card payment. REOS
     * funds the points it issued (the `points` clearing account).
     */
    private function payWithPoints(SessionBooking $booking, SessionOffer $offer, User $student): void
    {
        $product = $offer->productId($booking->minutes);
        $state = $this->states->current($student);
        $price = Price::query()->where('product_id', $product)->where('state_code', $state)->first()
            ?? Price::query()->where('product_id', $product)->orderBy('id')->first()
            ?? throw ValidationException::withMessages(['starts_at' => __('sessions.booking.errors.unavailable')]);

        $this->points->redeem($student, $booking->price_cents, "session:{$booking->uuid}");
        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'product_id' => $price->product_id, 'price_id' => $price->id, 'state_code' => $price->state_code,
            'amount_cents' => $booking->price_cents, 'discount_cents' => 0, 'tax_cents' => 0, 'total_cents' => $booking->price_cents, 'currency' => 'usd',
            'status' => OrderStatus::Paid, 'gateway' => self::POINTS, 'environment' => 'live', 'paid_at' => now(),
        ]);
        $booking->forceFill(['order_id' => $order->id])->save();
        $this->split->record($order);
        $this->money->orderPaid($order);
    }

    private function refund(SessionBooking $booking, User $actor, string $reason): void
    {
        $order = $booking->order;

        if ($order === null || $booking->refunded_at !== null) {
            return;
        }

        // Points come back as points, and the mentor's share is taken back (A13.1a).
        if ($booking->paid_with === 'points') {
            $this->points->giveBack($booking->student, (int) $booking->points, "session:{$booking->uuid}");
            $order->forceFill(['status' => OrderStatus::Refunded])->save();
            $this->split->reverse($order);
            $this->money->fullRefund($order);
            $booking->forceFill(['refunded_at' => now(), 'status' => $booking->status === 'no_show' ? 'no_show' : 'refunded'])->save();
            $this->audit->record('session_booking.points_returned', 'session_booking', $booking->uuid, ['points' => $booking->points, 'reason' => $reason], $actor);

            return;
        }

        try {
            $this->refunds->refund($order, null, $reason, $actor, bySessionRules: true);
            $booking->forceFill(['refunded_at' => now(), 'status' => $booking->status === 'no_show' ? 'no_show' : 'refunded'])->save();
        } catch (Throwable $e) {
            // The booking stays cancelled; REOS support refunds from screen 66.
            report($e);
        }
    }

    /**
     * Active instructors and mentors who take bookings, with their rating
     * (from ratings at least 24 hours old) and next free slot.
     *
     * @param  list<int>|null  $among
     * @return list<OfferRow>
     */
    public function offers(?int $only = null, ?array $among = null): array
    {
        $rows = [];
        $query = SessionOffer::query()->with('user')->where('active', true)
            ->when($only !== null, static fn ($q) => $q->where('user_id', $only))
            // Only these people (a brokerage's mentors, A10.3c).
            ->when($among !== null, static fn ($q) => $q->whereIn('user_id', $among ?? []));

        foreach ($query->get() as $offer) {
            $rated = SessionBooking::query()->where('instructor_id', $offer->user_id)->whereNotNull('rating')->where('rated_at', '<=', now()->subHours(self::RATING_DELAY_HOURS));
            $count = (clone $rated)->count();
            $average = (clone $rated)->avg('rating');
            $slots = $this->slots($offer, 60);
            $rows[] = [
                'id' => $offer->id, 'user_id' => $offer->user_id, 'name' => (string) $offer->user->name, 'school' => $this->sessions->hostOrganisation($offer->user)?->name,
                'bio' => $offer->bio, 'languages' => $offer->languages, 'topics' => $offer->topics, 'price_30' => $offer->price_30_cents, 'price_60' => $offer->price_60_cents,
                'rating' => $count === 0 || ! is_numeric($average) ? null : round((float) $average, 1), 'ratings' => $count, 'next_slot' => $slots[0] ?? null,
            ];
        }

        return $rows;
    }

    /** @return BookingRow */
    public function row(SessionBooking $b, User $viewer): array
    {
        $student = $viewer->id === $b->student_id;
        $offer = $b->status === 'held' && $student ? SessionOffer::query()->where('user_id', $b->instructor_id)->first() : null;

        return [
            'uuid' => $b->uuid, 'as' => $student ? 'student' : 'instructor', 'instructor' => (string) $b->instructor->name, 'student' => (string) $b->student->name,
            'starts_at' => $b->starts_at->toIso8601ZuluString(), 'ends_at' => $b->ends_at->toIso8601ZuluString(), 'minutes' => $b->minutes, 'topic' => $b->topic,
            'status' => $b->status, 'price' => $b->price_cents, 'session' => $b->session?->uuid,
            'can_cancel' => in_array($b->status, SessionBooking::BUSY, true) && $b->starts_at->isFuture(),
            'refund_on_cancel' => $b->price_cents > 0 && ($viewer->id === $b->instructor_id || self::refundOnCancel($b)),
            'can_rate' => $student && $b->status === 'completed' && $b->rated_at === null,
            'rating' => $b->rating, 'tasks_sent' => $b->tasks_sent_at !== null,
            'checkout_url' => $offer === null || $b->paid_with !== 'card' ? null : $this->checkoutUrl($b, $offer, $viewer),
            'paid_with' => $b->paid_with, 'points' => $b->points,
        ];
    }

    /**
     * The person's bookings: as a student and as an instructor, upcoming first.
     *
     * @return list<BookingRow>
     */
    public function for(User $user): array
    {
        return array_values(SessionBooking::query()->with(['student', 'instructor', 'session'])
            ->where(static fn ($q) => $q->where('student_id', $user->id)->orWhere('instructor_id', $user->id))
            ->whereIn('status', ['held', 'confirmed', 'completed', 'no_show', 'refunded', 'cancelled'])
            ->where('starts_at', '>=', now()->subDays(30))->orderBy('starts_at')->limit(100)->get()
            ->map(fn (SessionBooking $b): array => $this->row($b, $user))->all());
    }

    public static function canTeach(User $user): bool
    {
        return in_array(app(ActiveRole::class)->for($user)?->role, [Role::Instructor, Role::Mentor], true);
    }
}
