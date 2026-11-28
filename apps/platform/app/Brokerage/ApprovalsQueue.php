<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Mls\AgentPages;
use App\Models\BrokerQuestion;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use App\Social\PostApprovals;
use App\VirtualBroker\VirtualBroker;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One approvals queue (A15.3, broker journey screens 7–12, ADR 0072): posts
 * and public pages waiting for approval and agents' escalated questions,
 * oldest first, each with its checks.
 *
 * - **Who sees what:** the designated broker everything; an office admin
 *   the kinds their duties cover (approve posts: posts and pages; answer
 *   escalations: questions); a team leader the same for their team's
 *   agents only.
 * - **Overdue:** an item that has waited longer than the brokerage's limit
 *   in working hours (default 4) is marked, and the fallback passes it to
 *   the office admins (ApprovalFallbacks).
 *
 * Coordinator requests join the queue when transaction coordinators arrive
 * with the provider network.
 *
 * @phpstan-type Item array{kind: 'post'|'page'|'question', id: string, agent: string, summary: string, waiting_since: string|null, hours: float, overdue: bool, detail: array<string, mixed>}
 * @phpstan-type Queue array{brokerage: string, scope: 'brokerage'|'team', team: string|null, fallback_hours: int, can: array{posts: bool, questions: bool, settings: bool}, counts: array{posts: int, pages: int, questions: int, overdue: int}, items: list<Item>}
 */
final readonly class ApprovalsQueue
{
    public const DEFAULT_HOURS = 4;

    public function __construct(
        private ActiveRole $roles,
        private Memberships $memberships,
        private BrokerageStaff $staff,
        private PostApprovals $posts,
        private AgentPages $pages,
        private VirtualBroker $broker,
        private AuditLogger $audit,
    ) {}

    /** @return array{0: Organisation, 1: Organisation|null} the brokerage, and the team for a team leader */
    public function place(User $user): array
    {
        $membership = $this->roles->for($user);

        if ($membership === null || $membership->role !== Role::Broker) {
            throw new NotFoundHttpException;
        }

        $org = $membership->organisation;

        if ($org->type === OrganisationType::Team && $org->parent !== null && $org->parent->type === OrganisationType::Brokerage) {
            return [$org->parent, $org];
        }

        if ($org->type !== OrganisationType::Brokerage) {
            throw new NotFoundHttpException;
        }

        return [$org, null];
    }

    public static function fallbackHours(Organisation $brokerage): int
    {
        $hours = $brokerage->approval_settings['fallback_hours'] ?? null;

        return is_int($hours) && $hours >= 1 ? $hours : self::DEFAULT_HOURS;
    }

    /** @return Queue */
    public function for(User $user): array
    {
        [$brokerage, $team] = $this->place($user);
        $canPosts = $this->staff->can($user, $brokerage, 'approve_posts');
        $canQuestions = $this->staff->can($user, $brokerage, 'answer_escalations');
        $limit = self::fallbackHours($brokerage);
        $items = $this->items($brokerage, $team, $canPosts, $canQuestions, $limit);

        usort($items, static fn (array $a, array $b): int => [$b['hours']] <=> [$a['hours']]);

        return [
            'brokerage' => $brokerage->name, 'scope' => $team === null ? 'brokerage' : 'team', 'team' => $team?->name, 'fallback_hours' => $limit,
            'can' => ['posts' => $canPosts, 'questions' => $canQuestions, 'settings' => $team === null && $this->staff->isDesignated($brokerage, $user)],
            'counts' => [
                'posts' => count(array_filter($items, static fn (array $i): bool => $i['kind'] === 'post')),
                'pages' => count(array_filter($items, static fn (array $i): bool => $i['kind'] === 'page')),
                'questions' => count(array_filter($items, static fn (array $i): bool => $i['kind'] === 'question')),
                'overdue' => count(array_filter($items, static fn (array $i): bool => $i['overdue'])),
            ],
            'items' => $items,
        ];
    }

    /**
     * Every waiting item in a brokerage (or one team), for the queue and the fallback.
     *
     * @return list<Item>
     */
    public function items(Organisation $brokerage, ?Organisation $team, bool $posts, bool $questions, int $limit): array
    {
        $now = CarbonImmutable::now();
        $agents = $team === null ? null : $this->memberships->holdersIn(Role::Agent, $team)->pluck('id')->all();

        $items = [];

        if ($posts) {
            $names = $team === null ? null : $this->memberships->holdersIn(Role::Agent, $team)->pluck('name')->all();

            foreach ($this->posts->pending($brokerage) as $post) {
                if ($names === null || in_array($post['agent'], $names, true)) {
                    $items[] = self::item('post', $post['id'], $post['agent'], mb_strimwidth($post['body'], 0, 140, '…'), $post['requested_at'], $post, $now, $limit);
                }
            }

            foreach ($this->pages->pending($brokerage) as $page) {
                if ($names !== null && ! in_array($page['agent'], $names, true)) {
                    continue;
                }

                $items[] = self::item('page', $page['id'], $page['agent'], mb_strimwidth((string) $page['bio'], 0, 140, '…'), $page['requested_at'], $page, $now, $limit);
            }
        }

        if ($questions) {
            $waiting = BrokerQuestion::query()->where('organisation_id', $brokerage->id)->where('status', BrokerQuestion::WAITING)
                ->when($agents !== null, static fn ($q) => $q->whereIn('user_id', $agents))
                ->with(['user:id,name', 'deal:id,uuid,property', 'answerer:id,name'])->orderBy('escalated_at')->limit(100)->get();

            foreach ($waiting as $question) {
                $row = VirtualBroker::row($question);
                $items[] = self::item('question', $question->uuid, (string) $question->user->name, mb_strimwidth($question->question, 0, 140, '…'), $question->escalated_at?->toIso8601String(), $row, $now, $limit);
            }
        }

        return $items;
    }

    /**
     * @param  'post'|'page'|'question'  $kind
     * @param  array<string, mixed>  $detail
     * @return Item
     */
    private static function item(string $kind, string $id, string $agent, string $summary, ?string $since, array $detail, CarbonImmutable $now, int $limit): array
    {
        $hours = $since === null ? 0.0 : WorkingHours::between(CarbonImmutable::parse($since), $now);

        return ['kind' => $kind, 'id' => $id, 'agent' => $agent, 'summary' => $summary, 'waiting_since' => $since, 'hours' => $hours, 'overdue' => $hours >= $limit, 'detail' => $detail];
    }

    /**
     * Clear one item: approve or return a post or page (a note to return),
     * answer a question (optionally adding the answer to the brokerage's knowledge).
     *
     * @param  'approve'|'return'|'answer'  $action
     * @return Queue
     */
    public function act(User $user, string $kind, string $id, string $action, ?string $text, bool $addToKnowledge): array
    {
        [$brokerage] = $this->place($user);
        $queue = $this->for($user);
        $mine = array_filter($queue['items'], static fn (array $i): bool => $i['kind'] === $kind && $i['id'] === $id);

        if ($mine === []) {
            throw new NotFoundHttpException;
        }

        $valid = match ($kind) {
            'post', 'page' => in_array($action, ['approve', 'return'], true),
            default => $action === 'answer',
        };

        if (! $valid) {
            throw ValidationException::withMessages(['action' => __('brokerage.approvals.errors.action')]);
        }

        if (($action === 'return' || $action === 'answer') && trim((string) $text) === '') {
            throw ValidationException::withMessages(['text' => __($action === 'return' ? 'brokerage.approvals.errors.note' : 'brokerage.approvals.errors.answer')]);
        }

        match (true) {
            $kind === 'post' && $action === 'approve' => $this->posts->approve($user, $brokerage, $id),
            $kind === 'post' => $this->posts->sendBack($user, $brokerage, $id, trim((string) $text)),
            $kind === 'page' => $this->pages->decide($user, $brokerage, $id, $action === 'approve', $action === 'return' ? trim((string) $text) : null),
            default => $this->broker->reply(
                BrokerQuestion::query()->where('organisation_id', $brokerage->id)->where('uuid', $id)->firstOrFail(),
                $user, trim((string) $text), $addToKnowledge,
            ),
        };

        return $this->for($user);
    }

    /**
     * The working hours an item may wait before the office admins are told
     * (1 to 40). The designated broker only.
     *
     * @return Queue
     */
    public function setFallbackHours(User $user, int $hours): array
    {
        [$brokerage, $team] = $this->place($user);

        if ($team !== null) {
            throw new NotFoundHttpException;
        }

        $this->staff->designatedOnly($brokerage, $user);
        $before = self::fallbackHours($brokerage);
        $brokerage->approval_settings = [...($brokerage->approval_settings ?? []), 'fallback_hours' => $hours];
        $brokerage->save();
        $this->audit->record('approvals.settings', 'organisation', (string) $brokerage->id, ['before' => $before, 'after' => $hours], $user, $brokerage->id);

        return $this->for($user);
    }
}
