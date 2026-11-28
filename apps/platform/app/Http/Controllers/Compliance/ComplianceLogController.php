<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compliance;

use App\Authorization\Role;
use App\Compliance\ComplianceEngine;
use App\Compliance\ComplianceLog;
use App\Http\Controllers\Controller;
use App\Mentoring\MentorDirectory;
use App\Models\ComplianceEvent;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The compliance log (B13.2; broker web): every check, fix, request,
 * approval and send-back on every channel, filtered by agent, channel,
 * action and dates, and exported to CSV (the export is audited).
 */
final class ComplianceLogController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, MentorDirectory $directory, ComplianceLog $log): Response
    {
        $brokerage = $directory->brokerageOf($user, Role::Broker) ?? throw new NotFoundHttpException;
        $filters = self::filters($request, $brokerage, $directory);

        return Inertia::render('organisation/compliance-log', [
            'brokerage' => $brokerage->name,
            'events' => $log->list($brokerage, $filters),
            'filters' => $filters,
            'agents' => array_values(array_map(static fn (User $a): array => ['id' => $a->id, 'name' => (string) $a->name], User::query()->whereIn('id', $directory->agentsOf($brokerage))->orderBy('name')->get()->all())),
            // A provider's profile (B15.3) belongs to no brokerage's log.
            'channels' => array_values(array_diff(ComplianceEngine::CHANNELS, ['provider'])),
            'actions' => ComplianceEvent::ACTIONS,
        ]);
    }

    public function export(Request $request, #[CurrentUser] User $user, MentorDirectory $directory, ComplianceLog $log, AuditLogger $audit): StreamedResponse
    {
        $brokerage = $directory->brokerageOf($user, Role::Broker) ?? throw new NotFoundHttpException;
        $filters = self::filters($request, $brokerage, $directory);
        $audit->record('compliance_log.exported', 'organisation', (string) $brokerage->id, ['filters' => $filters], $user, $brokerage->id);

        return response()->streamDownload(static function () use ($log, $brokerage, $filters): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['when', 'agent', 'by', 'channel', 'action', 'item', 'rules', 'phrases', 'note'], escape: '');

            foreach ($log->export($brokerage, $filters) as $row) {
                fputcsv($out, array_map(self::cell(...), [
                    $row['at'], $row['agent'], $row['actor'] ?? '', $row['channel'], $row['action'], $row['subject'] ?? '',
                    implode('; ', array_unique(array_map(static fn (array $i): string => $i['code'].' ('.$i['citation'].')', $row['issues']))),
                    implode('; ', array_filter(array_map(static fn (array $i): ?string => $i['phrase'], $row['issues']))),
                    $row['note'] ?? '',
                ]), escape: '');
            }

            fclose($out);
        }, 'reos-compliance-log-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{agent: int|null, channel: string|null, action: string|null, from: string|null, to: string|null} */
    private static function filters(Request $request, Organisation $brokerage, MentorDirectory $directory): array
    {
        $request->validate([
            'agent' => ['nullable', 'integer', Rule::in($directory->agentsOf($brokerage))],
            'channel' => ['nullable', Rule::in(ComplianceEngine::CHANNELS)],
            'action' => ['nullable', Rule::in(ComplianceEvent::ACTIONS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            'agent' => $request->filled('agent') ? $request->integer('agent') : null,
            'channel' => $request->filled('channel') ? $request->string('channel')->toString() : null,
            'action' => $request->filled('action') ? $request->string('action')->toString() : null,
            'from' => $request->filled('from') ? $request->string('from')->toString() : null,
            'to' => $request->filled('to') ? $request->string('to')->toString() : null,
        ];
    }

    /** A spreadsheet never runs a cell as a formula. */
    private static function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
