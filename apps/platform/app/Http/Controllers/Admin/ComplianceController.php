<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\PrivacyRequests;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Compliance and audit (REOS super admin, web journey screen 71; A4.3b):
 * privacy requests with their 45-day clock, the platform rules, and the
 * platform audit log with filters and CSV export.
 */
final class ComplianceController extends Controller
{
    public function index(Request $request, AuditLog $log): Response
    {
        $filters = $this->filters($request);
        $page = $log->page($filters);

        return Inertia::render('admin/compliance/index', [
            'privacy' => PrivacyRequest::query()->with('user')
                ->orderByRaw("CASE WHEN status IN ('open', 'in_progress', 'hold') THEN 0 ELSE 1 END")
                ->orderBy('due_at')->limit(50)->get()
                ->map(static fn (PrivacyRequest $r): array => [
                    'id' => $r->id,
                    'kind' => $r->kind,
                    'person' => $r->name ?? $r->email,
                    'details' => $r->details,
                    'status' => $r->status,
                    'due_at' => $r->due_at->toIso8601String(),
                    'overdue' => $r->overdue(),
                    'extended' => $r->extended,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'response' => $r->response,
                ])->all(),
            'limitDays' => PrivacyRequests::DAYS,
            'dataMapReviewed' => config()->string('reos.privacy.data_map_reviewed'),
            'audit' => [
                'rows' => collect($page->items())->map(static fn (AuditEvent $e): array => self::row($e))->all(),
                'next' => $page->nextPageUrl(),
                'previous' => $page->previousPageUrl(),
            ],
            'filters' => $filters,
            'options' => [
                'groups' => array_keys(AuditLog::GROUPS),
                'tenants' => Organisation::query()->where('type', '!=', OrganisationType::Platform->value)->orderBy('name')->get(['id', 'name'])
                    ->map(static fn (Organisation $o): array => ['id' => $o->id, 'name' => $o->name])->all(),
            ],
        ]);
    }

    /** The audit log as CSV, with the screen's filters; audited itself. */
    public function export(Request $request, AuditLog $log, AuditLogger $audit, #[CurrentUser] User $user): StreamedResponse
    {
        $filters = $this->filters($request);
        $audit->record('audit.exported', 'audit_log', 'platform', ['filters' => array_filter($filters, static fn (mixed $v): bool => $v !== null)], $user);
        $rows = $log->all($filters);

        return response()->streamDownload(static function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['time', 'actor', 'actor_email', 'action', 'tenant', 'subject', 'before', 'after', 'ip_address', 'user_agent'], escape: '');

            foreach ($rows as $event) {
                [$before, $after] = AuditLog::change($event);
                fputcsv($out, array_map(self::safe(...), [
                    $event->created_at->toIso8601String(),
                    $event->actor === null ? 'system' : $event->actor->name,
                    $event->actor?->email,
                    $event->action,
                    $event->organisation?->name,
                    $event->subject_type.':'.$event->subject_id,
                    $before === null ? '' : (string) json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $after === null ? '' : (string) json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $event->ip_address,
                    $event->user_agent,
                ]), escape: '');
            }

            fclose($out);
        }, 'reos-audit-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function privacy(Request $request, PrivacyRequest $privacyRequest, PrivacyRequests $privacy, #[CurrentUser] User $user): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['start', 'done', 'decline', 'extend'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $privacy->act($privacyRequest, (string) $validated['action'], isset($validated['note']) && is_string($validated['note']) && trim($validated['note']) !== '' ? trim($validated['note']) : null, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('compliance.privacy.updated')]);

        return back();
    }

    /** @return array<string, mixed> */
    private static function row(AuditEvent $event): array
    {
        [$before, $after] = AuditLog::change($event);

        return [
            'id' => $event->id,
            'at' => $event->created_at->toIso8601String(),
            'actor' => $event->actor?->name,
            'action' => $event->action,
            'tenant' => $event->organisation?->name,
            'before' => $before,
            'after' => $after,
            'ip' => $event->ip_address,
        ];
    }

    /** @return array{q: string|null, group: string|null, tenant: int|null, from: string|null, to: string|null} */
    private function filters(Request $request): array
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', Rule::in(array_keys(AuditLog::GROUPS))],
            'tenant' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            'q' => $request->filled('q') ? $request->string('q')->toString() : null,
            'group' => $request->filled('group') ? $request->string('group')->toString() : null,
            'tenant' => $request->filled('tenant') ? $request->integer('tenant') : null,
            'from' => $request->filled('from') ? $request->string('from')->toString() : null,
            'to' => $request->filled('to') ? $request->string('to')->toString() : null,
        ];
    }

    /** Spreadsheets never run a cell as a formula. */
    private static function safe(mixed $cell): string
    {
        $text = is_scalar($cell) ? (string) $cell : '';

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }
}
