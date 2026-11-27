<?php

declare(strict_types=1);

namespace App\Feedback;

use App\Authorization\Role;
use App\Models\AgentFeedback;
use App\Models\AgentProfile;
use App\Models\Deal;
use App\Models\User;
use App\Notifications\AgentFeedbackReceived;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Feedback from agents (A13.3b, ADR 0056; agent screen 125).
 *
 * After a **verified closing** — the agent's deal is closed and the other
 * side's agent is a REOS agent found on the deal's parties by email — each
 * can rate the other on communication, responsiveness and professionalism,
 * with a comment. The rated agent sees nothing until there are **three
 * ratings**, so no one rating can be traced back; then the averages, the
 * comments (without names or deals) and a tip on what to improve. The
 * average is private unless they make it public on their agent page. They
 * can respond to a comment or report it (reported ones leave the average
 * until REOS support looks).
 *
 * @phpstan-type ToRate array{deal: string, property: string, closed_at: string|null, agent: array{id: int, name: string}}
 * @phpstan-type Item array{id: string, month: string, communication: int, responsiveness: int, professionalism: int, comment: string|null, response: string|null, reported: bool}
 * @phpstan-type Mine array{count: int, needed: int, shown: bool, average: array{overall: float, communication: float, responsiveness: float, professionalism: float}|null, items: list<Item>, tip: array{dimension: 'communication'|'responsiveness'|'professionalism', text: string, engine: 'claude'|'template'}|null, public: bool}
 */
final readonly class AgentFeedbacks
{
    /** Ratings needed before anything shows. */
    public const MIN = 3;

    public const DIMENSIONS = ['communication', 'responsiveness', 'professionalism'];

    /** How long after closing the other agent can be rated. */
    public const DAYS = 180;

    public function __construct(private FeedbackTips $tips, private AuditLogger $audit) {}

    /**
     * Agents I closed with and haven't rated yet.
     *
     * @return list<ToRate>
     */
    public function toRate(User $agent): array
    {
        $rated = AgentFeedback::query()->where('rater_id', $agent->id)->get(['deal_id', 'ratee_id'])
            ->map(static fn (AgentFeedback $f): string => $f->deal_id.':'.$f->ratee_id)->all();
        $out = [];

        foreach ($this->closedDeals($agent) as $deal) {
            foreach ($this->otherAgents($deal, $agent) as $other) {
                if (! in_array($deal->id.':'.$other->id, $rated, true)) {
                    $out[] = ['deal' => $deal->uuid, 'property' => $deal->property, 'closed_at' => $deal->closed_at?->toIso8601String(), 'agent' => ['id' => $other->id, 'name' => (string) $other->name]];
                }
            }
        }

        return $out;
    }

    /** @param array{communication: int, responsiveness: int, professionalism: int} $scores */
    public function rate(User $rater, string $dealUuid, int $rateeId, array $scores, ?string $comment): AgentFeedback
    {
        $deal = null;

        foreach ($this->closedDeals($rater) as $candidate) {
            if ($candidate->uuid === $dealUuid) {
                $deal = $candidate;
            }
        }

        $ratee = $deal === null ? null : array_values(array_filter($this->otherAgents($deal, $rater), static fn (User $u): bool => $u->id === $rateeId))[0] ?? null;

        if ($deal === null || $ratee === null) {
            throw ValidationException::withMessages(['deal' => __('feedback.errors.notVerified')]);
        }

        if (AgentFeedback::query()->where('deal_id', $deal->id)->where('rater_id', $rater->id)->where('ratee_id', $ratee->id)->exists()) {
            throw ValidationException::withMessages(['deal' => __('feedback.errors.already')]);
        }

        $feedback = AgentFeedback::query()->create([
            'uuid' => (string) Str::uuid7(), 'deal_id' => $deal->id, 'rater_id' => $rater->id, 'ratee_id' => $ratee->id,
            'communication' => self::score($scores['communication']), 'responsiveness' => self::score($scores['responsiveness']), 'professionalism' => self::score($scores['professionalism']),
            'comment' => $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 1000),
        ]);
        $ratee->notify(new AgentFeedbackReceived($this->counted($ratee) >= self::MIN));

        return $feedback;
    }

    /** @return Mine */
    public function mine(User $agent): array
    {
        $rows = AgentFeedback::query()->where('ratee_id', $agent->id)->orderByDesc('id')->get();
        $counted = $rows->filter(static fn (AgentFeedback $f): bool => $f->reported_at === null);
        $public = (bool) AgentProfile::query()->where('user_id', $agent->id)->value('feedback_public');
        $shown = $counted->count() >= self::MIN;

        if (! $shown) {
            return ['count' => $counted->count(), 'needed' => self::MIN, 'shown' => false, 'average' => null, 'items' => [], 'tip' => null, 'public' => $public];
        }

        $average = ['overall' => 0.0, 'communication' => 0.0, 'responsiveness' => 0.0, 'professionalism' => 0.0];

        foreach (self::DIMENSIONS as $dimension) {
            $average[$dimension] = round((float) $counted->avg($dimension), 1);
        }
        $average['overall'] = round(($average['communication'] + $average['responsiveness'] + $average['professionalism']) / 3, 1);

        $items = [];

        foreach ($rows->take(50) as $f) {
            $items[] = [
                'id' => $f->uuid, 'month' => ($f->created_at ?? CarbonImmutable::now())->format('Y-m'),
                'communication' => $f->communication, 'responsiveness' => $f->responsiveness, 'professionalism' => $f->professionalism,
                'comment' => $f->comment, 'response' => $f->response, 'reported' => $f->reported_at !== null,
            ];
        }

        $comments = array_values(array_filter($counted->pluck('comment')->all(), is_string(...)));

        return [
            'count' => $counted->count(), 'needed' => self::MIN, 'shown' => true, 'average' => $average, 'items' => $items,
            'tip' => $this->tips->tip($agent, $average, $comments), 'public' => $public,
        ];
    }

    /** A reply to one comment (shown with it). */
    public function respond(User $agent, string $uuid, string $text): void
    {
        $feedback = $this->own($agent, $uuid);
        $feedback->forceFill(['response' => mb_substr(trim($text), 0, 500), 'responded_at' => now()])->save();
    }

    /** Reports a rating, or takes the report back (Undo); reported ratings leave the average. */
    public function report(User $agent, string $uuid, ?string $reason): void
    {
        $feedback = $this->own($agent, $uuid);
        $feedback->forceFill($reason === null ? ['reported_at' => null, 'report_reason' => null] : ['reported_at' => now(), 'report_reason' => mb_substr(trim($reason), 0, 300)])->save();
        $this->audit->record($reason === null ? 'agent_feedback.unreported' : 'agent_feedback.reported', 'agent_feedback', $feedback->uuid, $reason === null ? [] : ['reason' => $feedback->report_reason], $agent);
    }

    /** Shows the average on the agent page, or hides it. */
    public function setPublic(User $agent, bool $public): void
    {
        AgentProfile::query()->updateOrCreate(['user_id' => $agent->id], ['feedback_public' => $public]);
    }

    /**
     * For the public agent page: only when the agent chose it and there are three ratings.
     *
     * @return array{average: float, count: int}|null
     */
    public function publicRating(User $agent): ?array
    {
        if (! (bool) AgentProfile::query()->where('user_id', $agent->id)->value('feedback_public')) {
            return null;
        }

        $mine = $this->counted($agent);

        if ($mine < self::MIN) {
            return null;
        }

        $row = AgentFeedback::query()->where('ratee_id', $agent->id)->whereNull('reported_at')
            ->selectRaw('avg(communication) as c, avg(responsiveness) as r, avg(professionalism) as p')->first();
        $c = is_numeric($row?->getAttribute('c')) ? (float) $row->getAttribute('c') : 0.0;
        $r = is_numeric($row?->getAttribute('r')) ? (float) $row->getAttribute('r') : 0.0;
        $p = is_numeric($row?->getAttribute('p')) ? (float) $row->getAttribute('p') : 0.0;

        return ['average' => round(($c + $r + $p) / 3, 1), 'count' => $mine];
    }

    private function counted(User $agent): int
    {
        return AgentFeedback::query()->where('ratee_id', $agent->id)->whereNull('reported_at')->count();
    }

    private function own(User $agent, string $uuid): AgentFeedback
    {
        return AgentFeedback::query()->where('ratee_id', $agent->id)->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;
    }

    /** @return list<Deal> */
    private function closedDeals(User $agent): array
    {
        return array_values(Deal::query()->where('user_id', $agent->id)->where('stage', 'closed')
            ->where(static fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', now()->subDays(self::DAYS)))
            ->orderByDesc('closed_at')->limit(100)->get()->all());
    }

    /**
     * The REOS agents on a deal's parties (an "agent" role with their email), other than me.
     *
     * @return list<User>
     */
    private function otherAgents(Deal $deal, User $me): array
    {
        $emails = [];

        foreach ($deal->parties as $party) {
            if (str_contains(mb_strtolower($party['role']), 'agent') && is_string($party['email'] ?? null) && $party['email'] !== '') {
                $emails[] = mb_strtolower(trim($party['email']));
            }
        }

        if ($emails === []) {
            return [];
        }

        $agents = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Role::Agent->value)->where('model_has_roles.model_type', (new User)->getMorphClass())->select('model_has_roles.model_id');

        return array_values(User::query()->whereIn(DB::raw('lower(email)'), array_values(array_unique($emails)))->whereIn('id', $agents)->where('id', '!=', $me->id)->get()->all());
    }

    private static function score(int $value): int
    {
        return max(1, min(5, $value));
    }
}
