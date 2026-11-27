<?php

declare(strict_types=1);

namespace App\Pods;

use App\Agents\Activity;
use App\Agents\AgentModes;
use App\Agents\AgentPlans;
use App\Mentoring\MentorDirectory;
use App\Messaging\Conversations;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Deal;
use App\Models\Pod;
use App\Models\PodGoal;
use App\Models\PodMember;
use App\Models\PodRequest;
use App\Models\User;
use App\Models\VideoSession;
use App\Notifications\PodMatched;
use App\Services\States\PersonStates;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Accountability pods (A13.2, ADR 0054; agent screen 131).
 *
 * An agent opts in from Mentors → Pod. REOS puts them in an open pod at
 * their own brokerage (or among independent agents) with the same state,
 * goal, language and part-time flag, or waits until five
 * agents can make one (three after a week of waiting). Each week members
 * set a goal and see each other's goals and the scorecard numbers they
 * chose to share (from their activity, never typed in), only inside the
 * pod. The pod has a weekly 15-minute call in REOS's video rooms (it ends
 * on time) and its own conversation in Messages. Leaving is undoable.
 *
 * @phpstan-type MemberRow array{id: int, name: string, me: bool, goal: string|null, metrics: list<array{key: string, done: float|int, target: float|int}>, shares: list<string>}
 * @phpstan-type PodView array{status: 'none'|'waiting'|'member', waiting_since: string|null, pod: array{id: string, name: string, goal: string, call: array{day: string, time: string, timezone: string, next: string|null, session: string|null}, conversation: string|null, week_of: string, members: list<MemberRow>}|null}
 */
final readonly class Pods
{
    public const WAIT_DAYS = 7;

    public const MIN_SIZE = 3;

    public const CALL_MINUTES = 15;

    public const SHARE = ['conversations', 'appointments', 'clients', 'ce_hours'];

    public function __construct(private AgentModes $modes, private AgentPlans $plans, private PersonStates $states, private VideoSessions $sessions, private Conversations $conversations, private MentorDirectory $directory) {}

    /** @return PodView */
    public function mine(User $agent, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $member = $this->membership($agent);

        if ($member === null) {
            $request = PodRequest::query()->where('user_id', $agent->id)->whereNull('matched_at')->whereNull('cancelled_at')->first();

            return ['status' => $request === null ? 'none' : 'waiting', 'waiting_since' => $request?->created_at?->toIso8601String(), 'pod' => null];
        }

        $pod = $member->pod;
        $week = self::weekOf($now, $pod->timezone);
        $goals = [];

        foreach (PodGoal::query()->where('pod_id', $pod->id)->where('week_of', $week->toDateString())->get() as $goal) {
            $goals[$goal->user_id] = $goal->text;
        }
        $members = [];

        foreach ($pod->members()->with('user')->orderBy('joined_at')->get() as $m) {
            $members[] = [
                'id' => $m->user_id, 'name' => (string) $m->user->name, 'me' => $m->user_id === $agent->id,
                'goal' => $goals[$m->user_id] ?? null,
                'metrics' => $this->metrics($m, $week), 'shares' => $m->share,
            ];
        }

        $call = $this->nextCall($pod, $now);
        $session = VideoSession::query()->where('pod_id', $pod->id)->where('starts_at', $call->utc())->first();

        return [
            'status' => 'member', 'waiting_since' => null,
            'pod' => [
                'id' => $pod->uuid, 'name' => $pod->name, 'goal' => $pod->goal,
                'call' => ['day' => $pod->call_day, 'time' => $pod->call_time, 'timezone' => $pod->timezone, 'next' => $call->toIso8601String(), 'session' => $session?->uuid],
                'conversation' => $pod->conversation_id === null ? null : Conversation::query()->whereKey($pod->conversation_id)->first()?->uuid,
                'week_of' => $week->toDateString(),
                'members' => $members,
            ],
        ];
    }

    /** Opt in: into an open pod now, or onto the waiting list. */
    public function join(User $agent, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();

        if ($this->membership($agent) !== null) {
            throw ValidationException::withMessages(['pod' => __('pods.errors.already')]);
        }

        $request = PodRequest::query()->where('user_id', $agent->id)->whereNull('matched_at')->whereNull('cancelled_at')->first()
            ?? PodRequest::query()->create(['user_id' => $agent->id, ...$this->profile($agent)]);
        $this->match($request, $now);
    }

    /** Leaves the pod or the waiting list (Undo rejoins the same pod). */
    public function leave(User $agent): ?string
    {
        PodRequest::query()->where('user_id', $agent->id)->whereNull('matched_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
        $member = $this->membership($agent);

        if ($member === null) {
            return null;
        }

        $member->forceFill(['left_at' => now()])->save();

        return $member->pod->uuid;
    }

    /** Undo: back into the same pod if there's room. */
    public function rejoin(User $agent, string $uuid): void
    {
        $pod = Pod::query()->where('uuid', $uuid)->firstOrFail();
        $member = PodMember::query()->where('pod_id', $pod->id)->where('user_id', $agent->id)->whereNotNull('left_at')->latest('id')->first() ?? throw new NotFoundHttpException;

        if ($this->membership($agent) !== null || $pod->members()->count() >= Pod::SIZE) {
            throw ValidationException::withMessages(['pod' => __('pods.errors.full')]);
        }

        $member->forceFill(['left_at' => null])->save();
    }

    /** This week's goal. */
    public function setGoal(User $agent, string $text, ?CarbonImmutable $now = null): void
    {
        $member = $this->membership($agent) ?? throw new NotFoundHttpException;
        $week = self::weekOf($now ?? CarbonImmutable::now(), $member->pod->timezone);
        PodGoal::query()->updateOrCreate(['pod_id' => $member->pod_id, 'user_id' => $agent->id, 'week_of' => $week->toDateString()], ['text' => mb_substr(trim($text), 0, 200)]);
    }

    /**
     * What the agent shares inside the pod.
     *
     * @param  list<string>  $metrics
     */
    public function share(User $agent, array $metrics): void
    {
        $member = $this->membership($agent) ?? throw new NotFoundHttpException;
        $member->forceFill(['share' => array_values(array_intersect(self::SHARE, $metrics))])->save();
    }

    /**
     * The daily run: waiting agents matched (three after a week), and each pod's next call booked.
     *
     * @return array{matched: int, calls: int}
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $matched = 0;

        foreach (PodRequest::query()->whereNull('matched_at')->whereNull('cancelled_at')->oldest('id')->get() as $request) {
            $matched += $this->match($request->refresh(), $now) ? 1 : 0;
        }

        $calls = 0;

        foreach (Pod::query()->whereHas('members')->get() as $pod) {
            $calls += $this->bookCall($pod, $now) ? 1 : 0;
        }

        return ['matched' => $matched, 'calls' => $calls];
    }

    /** The pod's call this week, booked in the video rooms for its members (ends on time). */
    public function bookCall(Pod $pod, CarbonImmutable $now): bool
    {
        $starts = $this->nextCall($pod, $now);

        if ($starts->gt($now->addDays(7)) || VideoSession::query()->where('pod_id', $pod->id)->where('starts_at', $starts->utc())->exists()) {
            return false;
        }

        $members = $pod->members()->with('user')->orderBy('joined_at')->get();

        if ($members->count() < 2) {
            return false;
        }

        $users = array_values($members->map(static fn (PodMember $m): User => $m->user)->all());

        if ($users === []) {
            return false;
        }

        $title = __('pods.call.title', ['name' => $pod->name], $pod->language);
        $this->sessions->podCall($pod, $users, is_string($title) ? $title : $pod->name, $starts, self::CALL_MINUTES);

        return true;
    }

    /** @return array{organisation_id: int|null, state_code: string, goal: string, language: string, part_time: bool} */
    private function profile(User $agent): array
    {
        return [
            'organisation_id' => $this->directory->brokerageOf($agent)?->id,
            'state_code' => $this->states->current($agent), 'goal' => $this->modes->goalOf($agent), 'language' => $agent->locale->value,
            'part_time' => in_array('part_time', $this->modes->flagsOf($agent), true),
        ];
    }

    /** Matches one waiting agent: an open pod, five waiting, or three after a week. */
    private function match(PodRequest $request, CarbonImmutable $now): bool
    {
        if ($request->matched_at !== null || $request->cancelled_at !== null) {
            return false;
        }

        // Matched inside the agent's brokerage (or among independent agents), by state, goal, language and part-time.
        $same = static fn ($q) => $q->where('organisation_id', $request->organisation_id)->where('state_code', $request->state_code)->where('goal', $request->goal)->where('language', $request->language)->where('part_time', $request->part_time);
        $open = Pod::query()->where($same)->withCount('members')->get()->first(static fn (Pod $p): bool => $p->members_count > 0 && $p->members_count < Pod::SIZE);

        if ($open !== null) {
            $this->add($open, [$request], $now);

            return true;
        }

        $waiting = PodRequest::query()->where($same)->whereNull('matched_at')->whereNull('cancelled_at')->oldest('id')->limit(Pod::SIZE)->get();
        $oldest = $waiting->first()?->created_at;

        if ($waiting->count() >= Pod::SIZE || ($waiting->count() >= self::MIN_SIZE && $oldest !== null && $oldest->lte($now->subDays(self::WAIT_DAYS)))) {
            $pod = Pod::query()->create([
                'uuid' => (string) Str::uuid7(), 'organisation_id' => $request->organisation_id, 'name' => __('pods.name', ['goal' => __("mentoring.goals.{$request->goal}", [], $request->language), 'n' => (string) (Pod::query()->where($same)->count() + 1)], $request->language),
                'state_code' => $request->state_code, 'goal' => $request->goal, 'language' => $request->language, 'part_time' => $request->part_time,
                'call_time' => $request->part_time ? '18:30' : '08:30',
            ]);
            $this->add($pod, array_values($waiting->all()), $now);
            $this->openConversation($pod);
            $this->bookCall($pod, $now);

            return true;
        }

        return false;
    }

    /** @param  list<PodRequest>  $requests */
    private function add(Pod $pod, array $requests, CarbonImmutable $now): void
    {
        foreach ($requests as $request) {
            PodMember::query()->create(['pod_id' => $pod->id, 'user_id' => $request->user_id, 'share' => self::SHARE, 'joined_at' => $now]);
            $request->forceFill(['matched_at' => $now])->save();
            $request->user->notify(new PodMatched($pod));
        }

        if ($pod->conversation_id !== null) {
            foreach ($requests as $request) {
                ConversationParticipant::query()->firstOrCreate(['conversation_id' => $pod->conversation_id, 'user_id' => $request->user_id], ['joined_at' => now()]);
            }
        }
    }

    /** The pod's own conversation in Messages. */
    private function openConversation(Pod $pod): void
    {
        $members = $pod->members()->with('user')->orderBy('joined_at')->get();
        $first = $members->first();

        if ($first === null || $members->count() < 2) {
            return;
        }

        try {
            $others = array_values(array_map(static fn (PodMember $m): int => $m->user_id, $members->skip(1)->all()));
            $welcome = __('pods.welcome', [], $pod->language);
            $conversation = $this->conversations->start($first->user, $others, is_string($welcome) ? $welcome : $pod->name, $pod->name);
            $pod->forceFill(['conversation_id' => $conversation->id])->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function membership(User $agent): ?PodMember
    {
        return PodMember::query()->where('user_id', $agent->id)->whereNull('left_at')->with('pod')->latest('id')->first();
    }

    /** @return list<array{key: string, done: float|int, target: float|int}> */
    private function metrics(PodMember $m, CarbonImmutable $week): array
    {
        $targets = $this->plans->targets($m->user, $this->modes->goalOf($m->user));
        $end = $week->addWeek();
        $rows = [];

        foreach ($m->share as $key) {
            $done = $key === 'clients'
                ? Deal::query()->where('user_id', $m->user_id)->whereIn('stage', ['active', 'under_contract'])->count()
                : Activity::value($key, $m->user, $week, $end);
            $rows[] = ['key' => $key, 'done' => $key === 'ce_hours' ? round($done, 1) : (int) $done, 'target' => $targets[$key] ?? 0];
        }

        return $rows;
    }

    private function nextCall(Pod $pod, CarbonImmutable $now): CarbonImmutable
    {
        $local = $now->setTimezone($pod->timezone);
        [$h, $i] = array_map('intval', explode(':', $pod->call_time));
        $day = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7][$pod->call_day] ?? 1;
        $call = $local->startOfWeek(CarbonImmutable::MONDAY)->addDays($day - 1)->setTime($h, $i);

        return $call->addMinutes(self::CALL_MINUTES)->lt($local) ? $call->addWeek() : $call;
    }

    public static function weekOf(CarbonImmutable $now, string $tz): CarbonImmutable
    {
        return $now->setTimezone($tz)->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }
}
