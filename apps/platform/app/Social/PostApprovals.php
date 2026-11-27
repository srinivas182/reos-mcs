<?php

declare(strict_types=1);

namespace App\Social;

use App\Authorization\Role;
use App\Compliance\ComplianceLog;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\PostApprovalDecided;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Studio\AdvertisingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Broker approval of posts (B12.1, ADR 0050; agent screen 167 "Send to
 * broker", broker web screen 35). When the brokerage asks to see an
 * agent's posts first, a post that passes its checks waits as "approval".
 * The broker approves it (it goes out now, or at its time) or sends it
 * back with a note (it returns to the agent's drafts). Every decision is
 * audited and the agent is told.
 *
 * @phpstan-type PendingRow array{id: string, agent: string, body: string, attribution: string, media: list<array{url: string, kind: string}>, channels: list<string>, scheduled_at: string|null, requested_at: string|null, issues: list<array{code: string, phrase: string, fix: string}>, reason: string|null}
 */
final readonly class PostApprovals
{
    public function __construct(private Memberships $memberships, private SocialPosts $posts, private AdvertisingPolicy $policy, private AuditLogger $audit, private ComplianceLog $log) {}

    /** @return list<PendingRow> */
    public function pending(Organisation $brokerage): array
    {
        $agents = $this->memberships->holdersIn(Role::Agent, $brokerage)->pluck('id')->all();
        $rows = [];

        foreach (SocialPost::query()->whereIn('user_id', $agents)->where('status', 'approval')->with(['targets', 'user'])->orderBy('approval_requested_at')->limit(100)->get() as $post) {
            $content = SocialPosts::content($post);
            $rows[] = [
                'id' => $post->uuid, 'agent' => (string) $post->user->name, 'body' => $post->body, 'attribution' => $post->attribution,
                'media' => array_map(static fn (array $m): array => ['url' => $m['url'], 'kind' => $m['kind']], $content['media']),
                'channels' => array_values(array_unique($post->targets->map(static fn ($t): string => $t->channel)->all())),
                'scheduled_at' => $post->scheduled_at?->toIso8601String(), 'requested_at' => $post->approval_requested_at?->toIso8601String(),
                'issues' => $this->policy->issues($post->user, $post->body),
                // The agent's reason when they sent flagged wording to the broker instead of fixing it (B13.2).
                'reason' => $post->approval_reason,
            ];
        }

        return $rows;
    }

    /** Approved: out now, or at its time. */
    public function approve(User $broker, Organisation $brokerage, string $uuid): void
    {
        $post = $this->find($brokerage, $uuid);
        $later = $post->scheduled_at !== null && CarbonImmutable::instance($post->scheduled_at)->isFuture();
        $post->forceFill(['status' => $later ? 'scheduled' : 'publishing', 'approved_by' => $broker->id, 'approved_at' => now(), 'approval_note' => null])->save();

        if (! $later) {
            $this->posts->publish($post);
        }

        $this->audit->record('social_post.approved', 'social_post', $post->uuid, ['agent' => $post->user_id], $broker, $brokerage->id);
        $this->log->record($post->user, 'post', 'approved', [], $broker, 'social_post', $post->uuid, $post->approval_reason);
        $post->user->notify(new PostApprovalDecided($post, true));
    }

    /** Sent back with a note: the agent's draft again. */
    public function sendBack(User $broker, Organisation $brokerage, string $uuid, string $note): void
    {
        $post = $this->find($brokerage, $uuid);
        $post->forceFill(['status' => 'draft', 'scheduled_at' => null, 'approved_by' => $broker->id, 'approved_at' => now(), 'approval_note' => mb_substr(trim($note), 0, 500)])->save();
        $this->audit->record('social_post.returned', 'social_post', $post->uuid, ['agent' => $post->user_id], $broker, $brokerage->id);
        $this->log->record($post->user, 'post', 'sent_back', [], $broker, 'social_post', $post->uuid, $post->approval_note);
        $post->user->notify(new PostApprovalDecided($post, false));
    }

    public function brokerageOf(User $broker): Organisation
    {
        foreach ($this->memberships->of($broker) as $membership) {
            if ($membership->role === Role::Broker && $membership->organisation->type === OrganisationType::Brokerage) {
                return $membership->organisation;
            }
        }

        throw new NotFoundHttpException;
    }

    private function find(Organisation $brokerage, string $uuid): SocialPost
    {
        $post = SocialPost::query()->where('uuid', $uuid)->with('user')->firstOrFail();

        if (! $this->memberships->holdersIn(Role::Agent, $brokerage)->contains('id', $post->user_id)) {
            throw new NotFoundHttpException;
        }

        if ($post->status !== 'approval') {
            throw ValidationException::withMessages(['post' => __('studio.errors.decided')]);
        }

        return $post;
    }
}
