<?php

declare(strict_types=1);

namespace App\Mentoring;

use App\Agents\AgentModes;
use App\Authorization\Role;
use App\Models\MentorAssignment;
use App\Models\MentorSetting;
use App\Models\Organisation;
use App\Models\SessionBooking;
use App\Models\SessionOffer;
use App\Models\User;
use App\Notifications\MentorAssigned;
use App\Notifications\MentorChangeRequested;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Sessions\SessionBookings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The brokerage's mentor program (A13.1a, ADR 0053; broker screens 21–23,
 * agent screens 114 and 116).
 *
 * Mentors are people with the Mentor role at the brokerage. The brokerage
 * sets each one's topics, prices (30 and 60 minutes), whether points are
 * accepted, how many mentees they take and who can book them (everyone at
 * the brokerage, or assigned mentees only). The mentor sets their own
 * hours (A10.2a). An agent's **assigned mentor** is free to them; every
 * assignment is audited and both people are told, with a first session
 * suggested. Agents can ask for a change. Mentors from other brokerages
 * are never shown.
 *
 * Matching (screen 23) scores each mentor with room for another mentee:
 * a shared language, topics that fit the agent's goal, and a lighter load.
 *
 * @phpstan-type MentorRow array{id: int, name: string, topics: list<string>, price_30: int, price_60: int, points: bool, max_mentees: int, who: string, mentees: int, rating: float|null, ratings: int, sessions: int, hours_set: bool}
 * @phpstan-type AgentRow array{id: int, name: string, goal: string, mentor: array{id: int, name: string}|null, change_requested: bool}
 * @phpstan-type MatchRow array{id: int, name: string, score: int, reasons: list<string>, room: int}
 * @phpstan-type Settings array{topics: list<string>, price_30_cents: int, price_60_cents: int, points: bool, max_mentees: int, who: string}
 */
final readonly class MentorProgram
{
    /** Topics a mentor can help with (shown in the agent's language). */
    public const TOPICS = ['first_deals', 'buyers', 'listings', 'contracts', 'negotiation', 'marketing', 'investors', 'leading_a_team'];

    /** Topics that fit each goal (screen 23's "focus"). */
    public const FIT = [
        'launch' => ['first_deals', 'buyers', 'contracts'],
        'grow' => ['listings', 'marketing', 'negotiation'],
        'lead' => ['leading_a_team', 'marketing'],
        'own' => ['investors', 'listings'],
    ];

    public function __construct(private Memberships $memberships, private MentorDirectory $directory, private SessionBookings $bookings, private AgentModes $modes, private AuditLogger $audit) {}

    /**
     * The broker's view (screen 21): mentors with load, price and rating; agents with their mentor.
     *
     * @return array{mentors: list<MentorRow>, agents: list<AgentRow>, topics: list<string>}
     */
    public function program(Organisation $brokerage): array
    {
        $mentors = [];

        foreach ($this->mentorsOf($brokerage) as $mentor) {
            $settings = $this->settings($brokerage, $mentor);
            $rated = SessionBooking::query()->where('instructor_id', $mentor->id)->whereNotNull('rating')->where('rated_at', '<=', now()->subHours(SessionBookings::RATING_DELAY_HOURS));
            $average = (clone $rated)->avg('rating');
            $mentors[] = [
                'id' => $mentor->id, 'name' => (string) $mentor->name, ...$this->settingsRow($settings),
                'mentees' => $this->load($brokerage, $mentor),
                'rating' => is_numeric($average) ? round((float) $average, 1) : null, 'ratings' => (clone $rated)->count(),
                'sessions' => SessionBooking::query()->where('instructor_id', $mentor->id)->where('status', 'completed')->count(),
                'hours_set' => SessionOffer::query()->where('user_id', $mentor->id)->where('active', true)->exists(),
            ];
        }

        $agents = [];

        foreach ($this->memberships->holdersIn(Role::Agent, $brokerage) as $agent) {
            $assignment = $this->assignment($brokerage, $agent);
            $agents[] = [
                'id' => $agent->id, 'name' => (string) $agent->name, 'goal' => $this->modes->goalOf($agent),
                'mentor' => $assignment === null ? null : ['id' => $assignment->mentor_id, 'name' => (string) $assignment->mentor->name],
                'change_requested' => $assignment?->change_requested_at !== null,
            ];
        }

        usort($agents, static fn (array $a, array $b): int => [$a['mentor'] !== null, $a['name']] <=> [$b['mentor'] !== null, $b['name']]);

        return ['mentors' => $mentors, 'agents' => $agents, 'topics' => self::TOPICS];
    }

    /**
     * Mentor settings (screen 22). The brokerage's prices become the mentor's session prices.
     *
     * @param  Settings  $values
     */
    public function save(Organisation $brokerage, User $mentor, array $values, User $actor): MentorSetting
    {
        $this->mustBeMentor($brokerage, $mentor);
        $settings = $this->settings($brokerage, $mentor);
        $before = $this->settingsRow($settings);
        $settings->forceFill([
            'topics' => array_values(array_intersect(self::TOPICS, $values['topics'])),
            'price_30_cents' => max(0, $values['price_30_cents']), 'price_60_cents' => max(0, $values['price_60_cents']),
            'points' => $values['points'], 'max_mentees' => max(1, min(30, $values['max_mentees'])),
            'who' => in_array($values['who'], MentorSetting::WHO, true) ? $values['who'] : 'everyone',
        ])->save();
        $this->bookings->setPrices($mentor, $settings->price_30_cents, $settings->price_60_cents, $settings->topics);
        $this->audit->record('mentor_program.updated', 'user', (string) $mentor->id, ['before' => $before, 'after' => $this->settingsRow($settings)], $actor, $brokerage->id);

        return $settings;
    }

    /**
     * Best mentors for an agent (screen 23), with the reasons.
     *
     * @return list<MatchRow>
     */
    public function matches(Organisation $brokerage, User $agent): array
    {
        $this->mustBeAgent($brokerage, $agent);
        $fit = self::FIT[$this->modes->goalOf($agent)] ?? [];
        $current = $this->assignment($brokerage, $agent)?->mentor_id;
        $rows = [];

        foreach ($this->mentorsOf($brokerage) as $mentor) {
            $settings = $this->settings($brokerage, $mentor);
            $room = $settings->max_mentees - $this->load($brokerage, $mentor) + ($current === $mentor->id ? 1 : 0);

            if ($room <= 0 || $mentor->id === $agent->id) {
                continue;
            }

            $speaks = SessionOffer::query()->where('user_id', $mentor->id)->first()->languages ?? ['en'];
            $reasons = [];
            $score = 0;

            if (in_array($agent->locale->value, $speaks, true)) {
                $reasons[] = 'language';
                $score += 3;
            }

            if (array_intersect($fit, $settings->topics) !== []) {
                $reasons[] = 'focus';
                $score += 2 * count(array_intersect($fit, $settings->topics));
            }

            $reasons[] = 'capacity';
            $score += min(3, $room);
            $rows[] = ['id' => $mentor->id, 'name' => (string) $mentor->name, 'score' => $score, 'reasons' => $reasons, 'room' => $room];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

        return $rows;
    }

    /** Assigns a mentor (screen 23): the earlier one ends, both people are told, and a first session is suggested. */
    public function assign(Organisation $brokerage, User $agent, User $mentor, User $actor): MentorAssignment
    {
        $match = array_values(array_filter($this->matches($brokerage, $agent), static fn (array $m): bool => $m['id'] === $mentor->id))[0]
            ?? throw ValidationException::withMessages(['mentor' => __('mentoring.errors.full')]);
        $before = $this->assignment($brokerage, $agent);

        $assignment = DB::transaction(function () use ($brokerage, $agent, $mentor, $actor, $match): MentorAssignment {
            MentorAssignment::query()->where('organisation_id', $brokerage->id)->where('agent_id', $agent->id)->whereNull('ended_at')->update(['ended_at' => now()]);

            return MentorAssignment::query()->create(['organisation_id' => $brokerage->id, 'mentor_id' => $mentor->id, 'agent_id' => $agent->id, 'assigned_by' => $actor->id, 'reasons' => $match['reasons']]);
        });

        $this->audit->record('mentor.assigned', 'user', (string) $agent->id, ['before' => $before?->mentor_id, 'after' => $mentor->id, 'reasons' => $match['reasons']], $actor, $brokerage->id);
        $agent->notify(new MentorAssigned($assignment, forMentor: false));
        $mentor->notify(new MentorAssigned($assignment, forMentor: true));

        return $assignment->load('mentor', 'agent');
    }

    /** The agent asks for another mentor; the brokers are told. */
    public function requestChange(User $agent): void
    {
        $brokerage = $this->directory->brokerageOf($agent) ?? throw new NotFoundHttpException;
        $assignment = $this->assignment($brokerage, $agent) ?? throw new NotFoundHttpException;
        $assignment->forceFill(['change_requested_at' => now()])->save();
        $this->audit->record('mentor.change_requested', 'user', (string) $agent->id, ['mentor' => $assignment->mentor_id], $agent, $brokerage->id);

        foreach ($this->memberships->holdersIn(Role::Broker, $brokerage) as $broker) {
            $broker->notify(new MentorChangeRequested($assignment));
        }
    }

    /** @return list<User> */
    private function mentorsOf(Organisation $brokerage): array
    {
        return array_values($this->memberships->holdersIn(Role::Mentor, $brokerage)->sortBy('name')->all());
    }

    private function settings(Organisation $brokerage, User $mentor): MentorSetting
    {
        return MentorDirectory::settings($brokerage, $mentor);
    }

    /** @return array{topics: list<string>, price_30: int, price_60: int, points: bool, max_mentees: int, who: string} */
    private function settingsRow(MentorSetting $s): array
    {
        return ['topics' => $s->topics, 'price_30' => $s->price_30_cents, 'price_60' => $s->price_60_cents, 'points' => $s->points, 'max_mentees' => $s->max_mentees, 'who' => $s->who];
    }

    private function load(Organisation $brokerage, User $mentor): int
    {
        return MentorAssignment::query()->where('organisation_id', $brokerage->id)->where('mentor_id', $mentor->id)->whereNull('ended_at')->count();
    }

    private function assignment(Organisation $brokerage, User $agent): ?MentorAssignment
    {
        return MentorDirectory::assignment($brokerage, $agent);
    }

    private function mustBeMentor(Organisation $brokerage, User $mentor): void
    {
        if (! $this->memberships->holdersIn(Role::Mentor, $brokerage)->contains('id', $mentor->id)) {
            throw new NotFoundHttpException;
        }
    }

    private function mustBeAgent(Organisation $brokerage, User $agent): void
    {
        if (! $this->memberships->holdersIn(Role::Agent, $brokerage)->contains('id', $agent->id)) {
            throw new NotFoundHttpException;
        }
    }
}
