<?php

declare(strict_types=1);

namespace App\Video;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Events\Video\SessionEnded;
use App\Messaging\Contacts;
use App\Messaging\Realtime;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Notifications\SessionBooked;
use App\Notifications\SessionStarting;
use App\Services\Authorization\Memberships;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Video sessions (B6.3, ADR 0024). Instructors and mentors schedule a
 * 1-on-1 session or a live class with people they work with (the same
 * relationships as Messages); everyone in it can join from five minutes
 * before the start; at the booked end the room closes for everyone.
 * Booking, payment and availability arrive with the marketplace sprints.
 *
 * @phpstan-import-type SessionData from SessionPresenter
 */
final readonly class VideoSessions
{
    /** Lengths offered, in minutes. */
    public const LENGTHS = [15, 30, 45, 60, 90, 120];

    /** "Starting soon" goes out this many minutes before. */
    public const REMIND_MINUTES = 10;

    public function __construct(
        private Memberships $memberships,
        private Contacts $contacts,
        private LiveKit $livekit,
    ) {}

    /** The organisation the person hosts sessions for, if they teach or mentor. */
    public function hostOrganisation(User $user): ?Organisation
    {
        $membership = array_values(array_filter($this->memberships->of($user), static fn (Membership $m): bool => in_array($m->role, [Role::Instructor, Role::Mentor], true)))[0] ?? null;

        return $membership?->organisation;
    }

    public function canHost(User $user): bool
    {
        return $this->hostOrganisation($user) !== null;
    }

    /**
     * @param  array{kind: string, title: string, agenda: string|null, starts_at: CarbonImmutable, minutes: int, guests: list<int>}  $plan
     */
    public function schedule(User $host, array $plan): VideoSession
    {
        $organisation = $this->hostOrganisation($host);

        if ($organisation === null) {
            throw ValidationException::withMessages(['guests' => __('sessions.errors.notHost')]);
        }

        $allowed = array_column($this->contacts->for($host), 'id');
        $guests = array_values(array_unique($plan['guests']));

        if ($guests === [] || array_diff($guests, $allowed) !== []) {
            throw ValidationException::withMessages(['guests' => __('sessions.errors.guests')]);
        }

        if ($plan['kind'] === VideoSession::ONE_ON_ONE && count($guests) !== 1) {
            throw ValidationException::withMessages(['guests' => __('sessions.errors.oneGuest')]);
        }

        // Stored in UTC whatever zone the time came in.
        $starts = $plan['starts_at']->utc();
        $ends = $starts->addMinutes($plan['minutes']);

        if ($starts->lessThan(now()->subMinute())) {
            throw ValidationException::withMessages(['starts_at' => __('sessions.errors.past')]);
        }

        if ($this->overlaps($host, $starts, $ends)) {
            throw ValidationException::withMessages(['starts_at' => __('sessions.errors.overlap')]);
        }

        $session = DB::transaction(function () use ($host, $organisation, $plan, $starts, $ends, $guests): VideoSession {
            $session = VideoSession::query()->create([
                'uuid' => (string) Str::uuid(),
                'organisation_id' => $organisation->id,
                'host_id' => $host->id,
                'kind' => $plan['kind'],
                'title' => $plan['title'],
                'agenda' => $plan['agenda'],
                'starts_at' => $starts,
                'ends_at' => $ends,
            ]);

            $session->participants()->create(['user_id' => $host->id, 'role' => VideoSessionParticipant::HOST]);

            foreach ($guests as $guest) {
                $session->participants()->create(['user_id' => $guest, 'role' => VideoSessionParticipant::GUEST]);
            }

            return $session;
        });

        Notification::send($this->people($session, except: $host->id), new SessionBooked($session, SessionBooked::BOOKED));

        return $session;
    }

    public function cancel(User $user, VideoSession $session): void
    {
        $this->ensureHost($user, $session);

        if ($session->over() || $session->status === VideoSession::LIVE) {
            throw ValidationException::withMessages(['session' => __('sessions.errors.cannotCancel')]);
        }

        $session->forceFill(['status' => VideoSession::CANCELLED])->save();
        Notification::send($this->people($session, except: $user->id), new SessionBooked($session, SessionBooked::CANCELLED));
    }

    /**
     * Sessions the person is in: coming up first, then the last month.
     *
     * @return Collection<int, VideoSession>
     */
    public function for(User $user): Collection
    {
        return VideoSession::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->where('ends_at', '>=', now()->subDays(30))
            ->with(['host', 'participants.user'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The next session the person is in, for Home and Today.
     *
     * @return SessionData|null
     */
    public function next(User $user): ?array
    {
        $session = VideoSession::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', [VideoSession::SCHEDULED, VideoSession::LIVE])
            ->where('ends_at', '>', now())
            ->with(['host', 'participants.user'])
            ->orderBy('starts_at')
            ->first();

        return $session === null ? null : SessionPresenter::present($session, $user);
    }

    public function participant(VideoSession $session, User $user): ?VideoSessionParticipant
    {
        return $session->participants()->where('user_id', $user->id)->first();
    }

    /** Only people in the session see it; to anyone else it doesn't exist. */
    public function ensureParticipant(VideoSession $session, User $user): VideoSessionParticipant
    {
        return $this->participant($session, $user) ?? throw new NotFoundHttpException;
    }

    /**
     * Entering the room: from five minutes before the start until the end.
     *
     * @return array{simulated: bool, url: string|null, token: string|null, ends_at: string}
     */
    public function join(VideoSession $session, User $user): array
    {
        $participant = $this->ensureParticipant($session, $user);

        if (! $session->open()) {
            throw ValidationException::withMessages(['session' => __($session->over() ? 'sessions.errors.over' : 'sessions.errors.notYet')]);
        }

        if (! $this->livekit->available()) {
            throw ValidationException::withMessages(['session' => __('sessions.errors.unavailable')]);
        }

        $now = now();
        $participant->forceFill([
            'first_joined_at' => $participant->first_joined_at ?? $now,
            'last_joined_at' => $now,
            'joins' => $participant->joins + 1,
        ])->save();

        if ($session->status === VideoSession::SCHEDULED) {
            $session->forceFill(['status' => VideoSession::LIVE, 'started_at' => $now])->save();
        }

        $room = $this->livekit->join($session, $user, $participant->role);

        return [
            'simulated' => $room === null,
            'url' => $room['url'] ?? null,
            'token' => $room['token'] ?? null,
            'ends_at' => $session->ends_at->toIso8601String(),
        ];
    }

    /** The host ends it early, or the booked end arrives. */
    public function end(VideoSession $session, ?User $by = null): void
    {
        if ($by !== null) {
            $this->ensureHost($by, $session);
        }

        if (in_array($session->status, [VideoSession::ENDED, VideoSession::CANCELLED], true)) {
            return;
        }

        $session->forceFill(['status' => VideoSession::ENDED, 'ended_at' => now()])->save();
        $this->livekit->close($session);
        $ids = [];

        foreach ($session->participants()->pluck('user_id') as $id) {
            $ids[] = is_numeric($id) ? (int) $id : 0;
        }

        Realtime::send(new SessionEnded($session, $ids));
    }

    /** Ends every session whose booked end has come (`reos:sessions:tick`, every minute). */
    public function endDue(): int
    {
        $due = VideoSession::query()->whereIn('status', [VideoSession::SCHEDULED, VideoSession::LIVE])->where('ends_at', '<=', now())->get();

        foreach ($due as $session) {
            $this->end($session);
        }

        return $due->count();
    }

    /** "Starting in 10 minutes" to everyone in it, once. */
    public function remindDue(): int
    {
        $due = VideoSession::query()->where('status', VideoSession::SCHEDULED)->whereNull('reminded_at')
            ->where('starts_at', '<=', now()->addMinutes(self::REMIND_MINUTES))->where('starts_at', '>', now()->subMinutes(self::REMIND_MINUTES))
            ->get();

        foreach ($due as $session) {
            $session->forceFill(['reminded_at' => now()])->save();
            Notification::send($this->people($session), new SessionStarting($session));
        }

        return $due->count();
    }

    /** Session notes (screen 16): shared by everyone in the session. */
    public function saveNotes(VideoSession $session, User $user, ?string $notes): void
    {
        $this->ensureParticipant($session, $user);

        if ($session->status === VideoSession::CANCELLED) {
            throw ValidationException::withMessages(['notes' => __('sessions.errors.cancelled')]);
        }

        $session->forceFill(['notes' => $notes, 'notes_updated_by' => $user->id, 'notes_updated_at' => now()])->save();
    }

    private function ensureHost(User $user, VideoSession $session): void
    {
        $participant = $this->ensureParticipant($session, $user);

        if ($participant->role !== VideoSessionParticipant::HOST) {
            throw ValidationException::withMessages(['session' => __('sessions.errors.hostOnly')]);
        }
    }

    private function overlaps(User $host, CarbonImmutable $starts, CarbonImmutable $ends): bool
    {
        return VideoSession::query()->where('host_id', $host->id)->whereIn('status', [VideoSession::SCHEDULED, VideoSession::LIVE])
            ->where('starts_at', '<', $ends)->where('ends_at', '>', $starts)->exists();
    }

    /** @return Collection<int, User> */
    private function people(VideoSession $session, ?int $except = null): Collection
    {
        $ids = $session->participants()->when($except !== null, fn ($q) => $q->where('user_id', '!=', $except))->pluck('user_id');

        return User::query()->whereIn('id', $ids)->get();
    }
}
