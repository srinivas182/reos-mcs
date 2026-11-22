<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Authorization\Role;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Notifications\MessagesReported;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Report and block (B6.1b), on every conversation. A block works both
 * ways for one-to-one messages: neither can message the other, and the
 * blocked person stops seeing read state. A report keeps a copy of the
 * messages (so editing or deleting them later can't hide them) and goes
 * to the tenant's admins and REOS.
 */
final readonly class Safety
{
    /** How many recent messages a report copies. */
    public const REPORT_MESSAGES = 20;

    public function __construct(
        private Tenancy $tenancy,
        private AuditLogger $audit,
        private Memberships $memberships,
    ) {}

    public function block(User $blocker, User $blocked): void
    {
        if ($blocker->id === $blocked->id) {
            throw ValidationException::withMessages(['user' => __('messages.errors.blockSelf')]);
        }

        DB::table('message_blocks')->insertOrIgnore(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id, 'created_at' => now()]);
    }

    public function unblock(User $blocker, User $blocked): void
    {
        DB::table('message_blocks')->where('blocker_id', $blocker->id)->where('blocked_id', $blocked->id)->delete();
    }

    /** Whether either has blocked the other. */
    public function between(int $a, int $b): bool
    {
        return DB::table('message_blocks')
            ->where(static fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(static fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))
            ->exists();
    }

    public function blocked(int $blocker, int $blocked): bool
    {
        return DB::table('message_blocks')->where('blocker_id', $blocker)->where('blocked_id', $blocked)->exists();
    }

    /**
     * Everyone this person blocked or was blocked by.
     *
     * @return list<int>
     */
    public function blockedWith(int $user): array
    {
        $ids = DB::table('message_blocks')->where('blocker_id', $user)->pluck('blocked_id')
            ->merge(DB::table('message_blocks')->where('blocked_id', $user)->pluck('blocker_id'));

        return array_values(array_unique($ids->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all()));
    }

    /** Reports the other person (or, in a group, a chosen participant) and, if asked, blocks them. */
    public function report(User $reporter, Conversation $conversation, string $reason, ?string $details, ?int $reportedUserId, bool $block): MessageReport
    {
        $participants = $conversation->participants()->whereNull('left_at')->pluck('user_id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();
        $others = array_values(array_diff($participants, [$reporter->id]));
        $reportedId = $reportedUserId ?? ($conversation->kind === Conversation::DIRECT ? ($others[0] ?? null) : null);

        if ($reportedId !== null && ! in_array($reportedId, $others, true)) {
            throw ValidationException::withMessages(['user' => __('messages.errors.reportWho')]);
        }

        $messages = Message::query()->with('sender')->where('conversation_id', $conversation->id)
            ->when($reportedId !== null, static fn ($q) => $q->where('user_id', $reportedId))
            ->latest('id')->limit(self::REPORT_MESSAGES)->get()->reverse()->values()
            ->map(static fn (Message $m): array => [
                'id' => $m->uuid,
                'sender' => $m->sender?->name,
                'body' => $m->deleted_at === null ? $m->body : null,
                'at' => $m->created_at->toIso8601String(),
            ])->all();

        // Written for the tenant the conversation started in, whatever the reporter's own scope (row-level security).
        $report = $this->tenancy->asPlatform(fn (): MessageReport => MessageReport::query()->create([
            'uuid' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'organisation_id' => $conversation->organisation_id,
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedId,
            'reason' => $reason,
            'details' => $details === null ? null : Str::limit($details, 1000, ''),
            'messages' => array_values($messages),
            'status' => 'open',
        ]));

        if ($block && $reportedId !== null) {
            $reported = User::query()->find($reportedId);

            if ($reported !== null) {
                $this->block($reporter, $reported);
            }
        }

        $this->audit->record('message.reported', 'message_report', $report->uuid, ['reason' => $reason, 'blocked' => $block], $reporter, $conversation->organisation_id);
        Notification::send($this->recipients($conversation->organisation_id), new MessagesReported($report));

        return $report;
    }

    public function resolve(MessageReport $report, User $by): void
    {
        $this->tenancy->asPlatform(fn () => $report->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $by->id])->save());
        $this->audit->record('message.report_resolved', 'message_report', $report->uuid, ['from' => 'open', 'to' => 'resolved'], $by, $report->organisation_id);
    }

    /**
     * The tenant's admins and REOS's super admins.
     *
     * @return list<User>
     */
    private function recipients(?int $organisationId): array
    {
        $people = $this->memberships->holders(Role::SuperAdmin)->all();

        if ($organisationId !== null) {
            $admins = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.organisation_id', $organisationId)
                ->whereIn('roles.name', [Role::Broker->value, Role::SchoolAdmin->value, Role::AssociationAdmin->value])
                ->pluck('model_has_roles.model_id');
            $people = [...$people, ...User::query()->whereIn('id', $admins)->get()->all()];
        }

        $unique = [];

        foreach ($people as $person) {
            $unique[$person->id] = $person;
        }

        return array_values($unique);
    }
}
