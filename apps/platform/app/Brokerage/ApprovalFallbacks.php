<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\ApprovalOverdue;
use App\Services\Audit\AuditLogger;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Office admin fallback (A15.3, ADR 0072): an item that waits longer than
 * the brokerage's limit in working hours is passed, once, to the designated
 * broker and the office admins whose duties cover it (approve posts for
 * posts and pages, answer escalations for questions). Each pass is recorded
 * so nobody is told twice, and audited.
 */
final readonly class ApprovalFallbacks
{
    public function __construct(private ApprovalsQueue $queue, private BrokerageStaff $staff, private Tenancy $tenancy, private AuditLogger $audit) {}

    /** Passes on every overdue item in every brokerage; returns how many were passed on. */
    public function run(): int
    {
        $sent = 0;

        foreach (Organisation::query()->where('type', OrganisationType::Brokerage)->get() as $brokerage) {
            $sent += (int) $this->tenancy->run($brokerage->id, fn (): int => $this->forBrokerage($brokerage));
        }

        return $sent;
    }

    public function forBrokerage(Organisation $brokerage): int
    {
        $limit = ApprovalsQueue::fallbackHours($brokerage);
        $overdue = array_filter($this->queue->items($brokerage, null, true, true, $limit), static fn (array $i): bool => $i['overdue']);

        if ($overdue === []) {
            return 0;
        }

        $done = DB::table('approval_fallbacks')->where('organisation_id', $brokerage->id)->get(['kind', 'ref'])
            ->map(static fn (object $r): string => $r->kind.':'.$r->ref)->all();
        $people = collect($this->staff->list($brokerage))->filter(static fn (array $p): bool => $p['team'] === null)->pluck('user_id')->all();
        $users = User::query()->whereIn('id', $people)->get();
        $sent = 0;

        foreach ($overdue as $item) {
            if (in_array($item['kind'].':'.$item['id'], $done, true)) {
                continue;
            }

            $duty = $item['kind'] === 'question' ? 'answer_escalations' : 'approve_posts';
            $to = $users->filter(fn (User $u): bool => $this->staff->can($u, $brokerage, $duty));

            foreach ($to as $user) {
                $user->notify(new ApprovalOverdue($item['kind'], $item['agent'], $limit));
            }

            DB::table('approval_fallbacks')->insert([
                'organisation_id' => $brokerage->id, 'kind' => $item['kind'], 'ref' => $item['id'], 'notified' => $to->count(),
                'sent_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record('approvals.fallback', $item['kind'], $item['id'], ['hours' => $item['hours'], 'notified' => $to->pluck('id')->values()->all()], null, $brokerage->id);
            $sent++;
        }

        return $sent;
    }
}
