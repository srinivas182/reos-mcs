<?php

declare(strict_types=1);

namespace App\Video\Classes;

use App\Events\Video\ClassChanged;
use App\Messaging\Realtime;
use App\Models\ClassCheck;
use App\Models\ClassCheckAnswer;
use App\Models\ClassMessage;
use App\Models\ClassPoll;
use App\Models\ClassPollVote;
use App\Models\ClassQuestion;
use App\Models\ClassQuestionLike;
use App\Models\LiveClass;
use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Notifications\ClassCheckReminder;
use App\Video\LiveKit;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Inside a live class (A10.2b; candidate L12, instructor i12): polls,
 * raised hands (the host brings a student on to speak), questions to the
 * instructor's queue with classmates' likes, chat, and attendance checks.
 * A CE class sends a check on the approval's interval; each stays open
 * a few minutes and anyone who joined and hasn't answered gets one
 * reminder. Every change goes to everyone in the room (`class.changed`).
 *
 * @phpstan-type ClassLive array{ce: bool, viewers: int, me: array{role: 'host'|'guest', hand: bool, on_stage: bool}, hands: list<array{id: int, name: string, since: string|null, on_stage: bool}>, polls: list<array{id: int, question: string, options: list<array{label: string, votes: int|null}>, total: int|null, my_vote: int|null, closed: bool}>, questions: list<array{id: int, body: string, by: string, likes: int, liked: bool, mine: bool, answered: bool}>, messages: list<array{id: int, by: string, body: string, at: string, mine: bool}>, check: array{id: int, closes_at: string, answered: bool}|null, checks: list<array{id: int, opens_at: string, closes_at: string, answered: int, of: int}>, next_check_at: string|null}
 */
final readonly class ClassRoom
{
    /** An attendance check stays open this many minutes. */
    public const CHECK_MINUTES = 3;

    /** The one reminder goes out this long after a check opens, if unanswered. */
    public const REMIND_AFTER_SECONDS = 60;

    /** Chat messages shown. */
    public const MESSAGES = 50;

    public function __construct(private VideoSessions $sessions, private LiveClasses $classes, private LiveKit $livekit) {}

    /** @return ClassLive */
    public function live(VideoSession $session, User $user): array
    {
        $class = $this->classes->classOf($session);
        $me = $this->sessions->ensureParticipant($session, $user);
        $host = $me->role === VideoSessionParticipant::HOST;
        $participants = $session->participants()->with('user')->get();
        $guests = $participants->where('role', VideoSessionParticipant::GUEST);

        $polls = [];

        foreach (ClassPoll::query()->where('video_session_id', $session->id)->with('votes')->latest('id')->limit(5)->get() as $poll) {
            $mine = $poll->votes->firstWhere('user_id', $user->id)?->option;
            // Students see results once they've answered or the poll closed.
            $show = $host || $mine !== null || $poll->closed_at !== null;
            $options = [];

            foreach ($poll->options as $i => $label) {
                $options[] = ['label' => $label, 'votes' => $show ? $poll->votes->where('option', $i)->count() : null];
            }

            $polls[] = ['id' => $poll->id, 'question' => $poll->question, 'options' => $options, 'total' => $show ? $poll->votes->count() : null, 'my_vote' => $mine, 'closed' => $poll->closed_at !== null];
        }

        $liked = ClassQuestionLike::query()->where('user_id', $user->id)
            ->whereIn('class_question_id', ClassQuestion::query()->where('video_session_id', $session->id)->select('id'))->pluck('class_question_id')->all();
        $questions = array_values(ClassQuestion::query()->where('video_session_id', $session->id)->with('user')
            ->orderByRaw('answered_at is not null')->orderByDesc('likes')->orderBy('id')->limit(100)->get()
            ->map(static fn (ClassQuestion $q): array => [
                'id' => $q->id, 'body' => $q->body, 'by' => $q->user->name ?? '', 'likes' => $q->likes,
                'liked' => in_array($q->id, $liked, true), 'mine' => $q->user_id === $user->id, 'answered' => $q->answered_at !== null,
            ])->all());

        $messages = array_values(ClassMessage::query()->where('video_session_id', $session->id)->with('user')->latest('id')->limit(self::MESSAGES)->get()->reverse()
            ->map(static fn (ClassMessage $m): array => ['id' => $m->id, 'by' => $m->user->name ?? '', 'body' => $m->body, 'at' => $m->created_at->toIso8601String(), 'mine' => $m->user_id === $user->id])->all());

        $current = ClassCheck::query()->where('video_session_id', $session->id)->where('opens_at', '<=', now())->where('closes_at', '>', now())->latest('id')->first();
        $checks = [];

        if ($host) {
            $joined = $guests->whereNotNull('first_joined_at')->count();

            foreach (ClassCheck::query()->where('video_session_id', $session->id)->withCount('answers')->orderBy('id')->get() as $check) {
                $checks[] = ['id' => $check->id, 'opens_at' => $check->opens_at->toIso8601String(), 'closes_at' => $check->closes_at->toIso8601String(), 'answered' => is_numeric($answers = $check->getAttribute('answers_count')) ? (int) $answers : 0, 'of' => $joined];
            }
        }

        $hands = $host ? array_values($guests->filter(static fn (VideoSessionParticipant $p): bool => $p->hand_raised_at !== null || $p->on_stage)
            ->sortBy(static fn (VideoSessionParticipant $p): int => $p->hand_raised_at?->getTimestamp() ?? 0)
            ->map(static fn (VideoSessionParticipant $p): array => ['id' => $p->user_id, 'name' => $p->user->name ?? '', 'since' => $p->hand_raised_at?->toIso8601String(), 'on_stage' => $p->on_stage])->all()) : [];

        $next = $host ? $this->nextCheckAt($session, $class) : null;

        return [
            'ce' => $class->ce(),
            'viewers' => $guests->where('in_room', true)->count(),
            'me' => ['role' => $host ? 'host' : 'guest', 'hand' => $me->hand_raised_at !== null, 'on_stage' => $me->on_stage],
            'hands' => $hands,
            'polls' => $polls,
            'questions' => $questions,
            'messages' => $messages,
            'check' => $current === null || $host ? null : ['id' => $current->id, 'closes_at' => $current->closes_at->toIso8601String(), 'answered' => $current->answers()->where('user_id', $user->id)->exists()],
            'checks' => $checks,
            'next_check_at' => $next?->toIso8601String(),
        ];
    }

    public function raiseHand(VideoSession $session, User $user, bool $raised): void
    {
        $participant = $this->guest($session, $user);
        $participant->forceFill(['hand_raised_at' => $raised ? now() : null])->save();
        $this->changed($session, 'hands');
    }

    /** The host brings a student on to speak and show video, or takes them off. */
    public function stage(VideoSession $session, User $host, int $userId, bool $on): void
    {
        $this->host($session, $host);
        $participant = $session->participants()->where('user_id', $userId)->where('role', VideoSessionParticipant::GUEST)->first() ?? throw new NotFoundHttpException;
        $participant->forceFill(['on_stage' => $on, 'hand_raised_at' => null])->save();
        $this->livekit->setSpeaker($session, $participant->user, $on);
        $this->changed($session, 'hands');
    }

    /** @param  list<string>  $options */
    public function poll(VideoSession $session, User $host, string $question, array $options): ClassPoll
    {
        $this->host($session, $host);
        $this->ensureOpen($session);
        // One poll open at a time: launching another closes the last.
        ClassPoll::query()->where('video_session_id', $session->id)->whereNull('closed_at')->update(['closed_at' => now()]);
        $poll = ClassPoll::query()->create(['video_session_id' => $session->id, 'question' => $question, 'options' => $options]);
        $this->changed($session, 'polls');

        return $poll;
    }

    public function closePoll(VideoSession $session, User $host, ClassPoll $poll): void
    {
        $this->host($session, $host);
        $this->ofSession($session, $poll->video_session_id);
        $poll->forceFill(['closed_at' => $poll->closed_at ?? now()])->save();
        $this->changed($session, 'polls');
    }

    public function vote(VideoSession $session, User $user, ClassPoll $poll, int $option): void
    {
        $this->guest($session, $user);
        $this->ofSession($session, $poll->video_session_id);

        if ($poll->closed_at !== null || ! array_key_exists($option, $poll->options)) {
            throw ValidationException::withMessages(['option' => __('classes.errors.pollClosed')]);
        }

        $voted = ClassPollVote::query()->where('class_poll_id', $poll->id)->where('user_id', $user->id)->exists();

        if (! $voted) {
            try {
                // Its own savepoint: a vote racing this one leaves the request's transaction usable.
                DB::transaction(fn () => ClassPollVote::query()->create(['class_poll_id' => $poll->id, 'user_id' => $user->id, 'option' => $option]));
            } catch (UniqueConstraintViolationException) {
                $voted = true;
            }
        }

        if ($voted) {
            throw ValidationException::withMessages(['option' => __('classes.errors.voted')]);
        }

        $this->changed($session, 'polls');
    }

    public function ask(VideoSession $session, User $user, string $body): ClassQuestion
    {
        $this->guest($session, $user);
        $this->ensureOpen($session);
        $question = ClassQuestion::query()->create(['video_session_id' => $session->id, 'user_id' => $user->id, 'body' => $body]);
        $this->changed($session, 'questions');

        return $question;
    }

    /** Like a classmate's question, or take the like back. */
    public function like(VideoSession $session, User $user, ClassQuestion $question, bool $like): void
    {
        $this->guest($session, $user);
        $this->ofSession($session, $question->video_session_id);

        DB::transaction(function () use ($user, $question, $like): void {
            $existing = ClassQuestionLike::query()->where('class_question_id', $question->id)->where('user_id', $user->id)->first();

            if ($like && $existing === null) {
                ClassQuestionLike::query()->create(['class_question_id' => $question->id, 'user_id' => $user->id]);
            } elseif (! $like && $existing !== null) {
                $existing->delete();
            }

            $question->forceFill(['likes' => ClassQuestionLike::query()->where('class_question_id', $question->id)->count()])->save();
        });

        $this->changed($session, 'questions');
    }

    public function answered(VideoSession $session, User $host, ClassQuestion $question, bool $answered): void
    {
        $this->host($session, $host);
        $this->ofSession($session, $question->video_session_id);
        $question->forceFill(['answered_at' => $answered ? ($question->answered_at ?? now()) : null])->save();
        $this->changed($session, 'questions');
    }

    public function chat(VideoSession $session, User $user, string $body): void
    {
        $this->sessions->ensureParticipant($session, $user);
        $this->ensureOpen($session);
        ClassMessage::query()->create(['video_session_id' => $session->id, 'user_id' => $user->id, 'body' => $body]);
        $this->changed($session, 'chat');
    }

    /** The host sends an attendance check now. */
    public function sendCheck(VideoSession $session, User $host): ClassCheck
    {
        $this->host($session, $host);
        $this->ensureOpen($session);

        if (ClassCheck::query()->where('video_session_id', $session->id)->where('closes_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['check' => __('classes.errors.checkOpen')]);
        }

        return $this->openCheck($session, $host->id);
    }

    /** "I'm here." */
    public function answerCheck(VideoSession $session, User $user, ClassCheck $check): void
    {
        $this->guest($session, $user);
        $this->ofSession($session, $check->video_session_id);

        if (! $check->open()) {
            throw ValidationException::withMessages(['check' => __('classes.errors.checkClosed')]);
        }

        ClassCheckAnswer::query()->firstOrCreate(['class_check_id' => $check->id, 'user_id' => $user->id], ['answered_at' => now()]);
        $this->changed($session, 'checks');
    }

    /**
     * Every minute (`reos:sessions:tick`): opens the checks a CE class is
     * due on its interval, and sends the one reminder for each check.
     *
     * @return array{opened: int, reminded: int}
     */
    public function tick(): array
    {
        $opened = 0;
        $classes = LiveClass::query()->whereNotNull('checkin_minutes')
            ->whereHas('session', fn ($q) => $q->where('status', VideoSession::LIVE)->where('ends_at', '>', now()))->with('session')->get();

        foreach ($classes as $class) {
            $due = $this->nextCheckAt($class->session, $class);

            if ($due !== null && $due->lessThanOrEqualTo(now())) {
                $this->openCheck($class->session, null);
                $opened++;
            }
        }

        $reminded = 0;
        $checks = ClassCheck::query()->whereNull('reminded_at')->where('opens_at', '<=', now()->subSeconds(self::REMIND_AFTER_SECONDS))->where('closes_at', '>', now())->get();

        foreach ($checks as $check) {
            $check->forceFill(['reminded_at' => now()])->save();
            $session = VideoSession::query()->find($check->video_session_id);

            if ($session === null) {
                continue;
            }

            $answered = $check->answers()->pluck('user_id')->all();
            $missing = $session->participants()->where('role', VideoSessionParticipant::GUEST)->whereNotNull('first_joined_at')->whereNotIn('user_id', $answered)->with('user')->get();

            foreach ($missing as $participant) {
                $participant->user->notify(new ClassCheckReminder($session, $check));
                $reminded++;
            }
        }

        return ['opened' => $opened, 'reminded' => $reminded];
    }

    /**
     * When a CE class's next automatic check is due: the interval after the
     * last check (or the start), if it can still close before the end.
     */
    public function nextCheckAt(VideoSession $session, LiveClass $class): ?CarbonImmutable
    {
        if ($class->checkin_minutes === null || $session->over()) {
            return null;
        }

        $last = ClassCheck::query()->where('video_session_id', $session->id)->max('opens_at');
        $from = is_string($last) ? CarbonImmutable::parse($last) : $session->starts_at;
        $next = $from->addMinutes($class->checkin_minutes);

        return $next->addMinutes(self::CHECK_MINUTES)->lessThanOrEqualTo($session->ends_at) ? $next : null;
    }

    private function openCheck(VideoSession $session, ?int $by): ClassCheck
    {
        $check = ClassCheck::query()->create(['video_session_id' => $session->id, 'sent_by' => $by, 'opens_at' => now(), 'closes_at' => now()->addMinutes(self::CHECK_MINUTES)]);
        $this->changed($session, 'checks');

        return $check;
    }

    private function host(VideoSession $session, User $user): VideoSessionParticipant
    {
        $this->classes->classOf($session);
        $participant = $this->sessions->ensureParticipant($session, $user);

        if ($participant->role !== VideoSessionParticipant::HOST) {
            throw ValidationException::withMessages(['class' => __('sessions.errors.hostOnly')]);
        }

        return $participant;
    }

    private function guest(VideoSession $session, User $user): VideoSessionParticipant
    {
        $this->classes->classOf($session);
        $participant = $this->sessions->ensureParticipant($session, $user);

        if ($participant->role !== VideoSessionParticipant::GUEST) {
            throw ValidationException::withMessages(['class' => __('classes.errors.studentsOnly')]);
        }

        $this->ensureOpen($session);

        return $participant;
    }

    /** Class tools work while the room is open. */
    private function ensureOpen(VideoSession $session): void
    {
        if (! $session->open()) {
            throw ValidationException::withMessages(['class' => __($session->over() ? 'sessions.errors.over' : 'sessions.errors.notYet')]);
        }
    }

    private function ofSession(VideoSession $session, int $id): void
    {
        if ($session->id !== $id) {
            throw new NotFoundHttpException;
        }
    }

    private function changed(VideoSession $session, string $what): void
    {
        $ids = [];

        foreach ($session->participants()->where(fn ($q) => $q->where('in_room', true)->orWhere('role', VideoSessionParticipant::HOST))->pluck('user_id') as $id) {
            $ids[] = is_numeric($id) ? (int) $id : 0;
        }

        Realtime::send(new ClassChanged($session, $ids, $what));
    }
}
