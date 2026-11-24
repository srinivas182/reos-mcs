<?php

declare(strict_types=1);

namespace App\Licences\Ce;

use App\Content\CourseType;
use App\Content\Delivery;
use App\Enums\LicenceType;
use App\Integrations\Catalogue;
use App\Integrations\IntegrationMode;
use App\Models\CeCompletion;
use App\Models\CeReport;
use App\Models\CeReportLine;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\CourseProgress;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Integrations\IntegrationHub;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CE reporting to TREC (A11.3, ADR 0045; school admin web screen 51).
 *
 * A school reports its CE courses' completions for a date range in one of
 * two ways:
 *
 * - **file:** REOS builds TREC's CE Posting System upload (CSV), the school
 *   uploads it, and the report waits as "Pending upload" until the school
 *   enters TREC's confirmation reference;
 * - **reos:** with the school's authority, REOS submits on its behalf
 *   (`trec-ce-reporting`); the reference comes back the same way.
 *
 * Entering the reference moves every completion in the report to Reported.
 * A completion TREC rejects is marked with the reason and returns to Ready,
 * so it goes in the next report once fixed. Every submission is audited.
 *
 * @phpstan-type Row array{completion: int, student: string, licence: string|null, course: string, course_number: string|null, hours: float, completed_on: string, problem: 'no_licence'|'no_approval'|null, rejected: string|null}
 * @phpstan-type Summary array{id: string, from_on: string, to_on: string, method: string, status: string, reference: string|null, count: int, reported: int, rejected: int, created_by: string|null, created_at: string|null, lines: list<array{completion: int, student: string, course: string, status: string, reason: string|null}>}
 */
final readonly class CeReporting
{
    public const INTEGRATION = 'trec-ce-reporting';

    /** TREC's CE Posting System upload, in column order (dates MMDDYYYY, kept as text). */
    public const COLUMNS = ['License Type', 'License Number', 'Course Number', 'Delivery Method', 'Start Date', 'Completion Date'];

    public function __construct(
        private AuditLogger $audit,
        private IntegrationHub $hub,
        private Catalogue $catalogue,
        private Application $app,
    ) {}

    /** @return array{ready: list<Row>, rejected: list<Row>, reports: list<Summary>, reos: 'available'|'simulated'|'unavailable'} */
    public function overview(Organisation $school): array
    {
        $ready = $this->ready($school);

        return [
            'ready' => array_values(array_filter($ready, static fn (array $r): bool => $r['rejected'] === null)),
            'rejected' => array_values(array_filter($ready, static fn (array $r): bool => $r['rejected'] !== null)),
            'reports' => array_values(CeReport::query()->where('organisation_id', $school->id)->with(['creator:id,name', 'lines.completion.user:id,name', 'lines.completion.course:id,title'])
                ->latest('id')->limit(50)->get()->map(fn (CeReport $r): array => $this->summary($r))->all()),
            'reos' => $this->reosMode(),
        ];
    }

    /** A report of the Ready completions finished in the range. */
    public function create(Organisation $school, User $actor, string $from, string $to, string $method): CeReport
    {
        $mode = $this->reosMode();

        if ($method === 'reos' && $mode === 'unavailable') {
            throw ValidationException::withMessages(['method' => self::line('ce.reports.errors.reosUnavailable')]);
        }

        $rows = array_values(array_filter($this->ready($school), static fn (array $r): bool => $r['problem'] === null && $r['completed_on'] >= $from && $r['completed_on'] <= $to));

        if ($rows === []) {
            throw ValidationException::withMessages(['from_on' => self::line('ce.reports.errors.nothing')]);
        }

        $report = DB::transaction(function () use ($school, $actor, $from, $to, $method, $rows): CeReport {
            $report = CeReport::query()->create([
                'uuid' => (string) Str::uuid7(), 'organisation_id' => $school->id, 'state_code' => 'TX', 'from_on' => $from, 'to_on' => $to,
                'method' => $method, 'status' => $method === 'file' ? 'pending_upload' : 'submitted', 'created_by' => $actor->id,
                'submitted_at' => $method === 'reos' ? now() : null,
            ]);

            foreach ($rows as $row) {
                CeReportLine::query()->create(['ce_report_id' => $report->id, 'ce_completion_id' => $row['completion'], 'licence_number' => $row['licence'], 'course_number' => $row['course_number'], 'status' => 'included']);
            }

            return $report;
        });

        $this->audit->record('ce_report.created', 'ce_report', $report->uuid, ['method' => $method, 'from' => $from, 'to' => $to, 'completions' => count($rows)], $actor, $school->id);

        // On the Simulator, TREC's confirmation comes straight back.
        if ($method === 'reos' && $mode === 'simulated') {
            $this->confirm($report, 'SIM-'.strtoupper(Str::random(8)), $actor);
        }

        return $report->refresh();
    }

    /** TREC's confirmation reference: every completion still in the report is Reported. */
    public function confirm(CeReport $report, string $reference, User $actor): CeReport
    {
        DB::transaction(static function () use ($report, $reference): void {
            $report->forceFill(['status' => 'reported', 'reference' => $reference, 'reported_at' => now()])->save();
            CeReportLine::query()->where('ce_report_id', $report->id)->where('status', 'included')->update(['status' => 'reported', 'updated_at' => now()]);
        });

        $this->audit->record('ce_report.reported', 'ce_report', $report->uuid, ['reference' => $reference], $actor, $report->organisation_id);

        return $report;
    }

    /** TREC rejected one completion (or Undo puts it back): it returns to Ready with the reason. */
    public function reject(CeReport $report, int $completion, bool $rejected, ?string $reason, User $actor): CeReportLine
    {
        $line = CeReportLine::query()->where('ce_report_id', $report->id)->where('ce_completion_id', $completion)->firstOrFail();
        $line->forceFill(['status' => $rejected ? 'rejected' : ($report->status === 'reported' ? 'reported' : 'included'), 'reason' => $rejected ? $reason : null])->save();
        $this->audit->record($rejected ? 'ce_report.rejected' : 'ce_report.restored', 'ce_report', $report->uuid, ['completion' => $completion, 'reason' => $rejected ? $reason : null], $actor, $report->organisation_id);

        return $line;
    }

    /** TREC's upload file for a report: one row per completion still in it. */
    public function csv(CeReport $report): string
    {
        $lines = CeReportLine::query()->where('ce_report_id', $report->id)->whereIn('status', ['included', 'reported'])->with('completion.course', 'completion.user')->orderBy('id')->get();
        $out = fopen('php://temp', 'r+');

        if ($out === false) {
            return '';
        }

        fputcsv($out, self::COLUMNS, escape: '');

        foreach ($lines as $line) {
            $completion = $line->completion;
            $course = $completion->course;
            $licence = Licence::query()->where('user_id', $completion->user_id)->where('state_code', $report->state_code)->first();
            $started = $course === null ? null : CourseProgress::query()->where('user_id', $completion->user_id)->where('course_id', $course->id)->first()?->created_at;
            $approval = $course === null ? null : $this->approval($course, $report->state_code);
            fputcsv($out, [
                $licence?->type === LicenceType::Broker ? 'BRK' : 'SALE',
                $line->licence_number ?? '',
                $line->course_number ?? '',
                $approval !== null && in_array($approval->delivery, [Delivery::Classroom, Delivery::LiveOnline], true) ? 'Classroom' : 'Distance Learning',
                ($started === null ? CarbonImmutable::parse($completion->completed_on) : CarbonImmutable::instance($started))->format('mdY'),
                CarbonImmutable::parse($completion->completed_on)->format('mdY'),
            ], escape: '');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return is_string($csv) ? $csv : '';
    }

    /**
     * Completions of the school's CE courses not yet in a report (or rejected from one).
     *
     * @return list<Row>
     */
    private function ready(Organisation $school): array
    {
        $courses = Course::query()->where('organisation_id', $school->id)->where('type', CourseType::Ce)->with('approvals')->get()->keyBy('id');
        $completions = CeCompletion::query()->whereIn('course_id', $courses->keys()->all())->where('state_code', 'TX')
            ->whereDoesntHave('reportLines', static fn ($q) => $q->whereIn('status', ['included', 'reported']))
            ->with('user:id,name')->orderBy('completed_on')->orderBy('id')->limit(1000)->get();
        $licences = Licence::query()->whereIn('user_id', $completions->pluck('user_id')->unique()->all())->where('state_code', 'TX')->pluck('number', 'user_id');
        $rejections = CeReportLine::query()->whereIn('ce_completion_id', $completions->pluck('id')->all())->where('status', 'rejected')->orderBy('id')->pluck('reason', 'ce_completion_id');
        $rows = [];

        foreach ($completions as $completion) {
            $course = $courses->get($completion->course_id);
            $approval = $course === null ? null : $this->approval($course, 'TX');
            $licence = $licences->get($completion->user_id);
            $rows[] = [
                'completion' => $completion->id,
                'student' => (string) $completion->user->name,
                'licence' => is_string($licence) ? $licence : null,
                'course' => $course->title ?? $completion->title,
                'course_number' => $approval?->number,
                'hours' => (float) $completion->hours,
                'completed_on' => $completion->completed_on->toDateString(),
                'problem' => match (true) {
                    ! is_string($licence) => 'no_licence',
                    $approval?->number === null => 'no_approval',
                    default => null,
                },
                'rejected' => $rejections->has($completion->id) ? self::text($rejections->get($completion->id)) : null,
            ];
        }

        return $rows;
    }

    /** @return Summary */
    private function summary(CeReport $report): array
    {
        $lines = [];

        foreach ($report->lines as $line) {
            $lines[] = ['completion' => $line->ce_completion_id, 'student' => (string) $line->completion->user->name, 'course' => $line->completion->course->title ?? $line->completion->title, 'status' => $line->status, 'reason' => $line->reason];
        }

        return [
            'id' => $report->uuid, 'from_on' => substr($report->from_on, 0, 10), 'to_on' => substr($report->to_on, 0, 10), 'method' => $report->method, 'status' => $report->status,
            'reference' => $report->reference, 'count' => count($lines), 'reported' => count(array_filter($lines, static fn (array $l): bool => $l['status'] === 'reported')),
            'rejected' => count(array_filter($lines, static fn (array $l): bool => $l['status'] === 'rejected')), 'created_by' => $report->creator?->name, 'created_at' => $report->created_at?->toIso8601String(),
            'lines' => $lines,
        ];
    }

    private function approval(Course $course, string $state): ?CourseApproval
    {
        return $course->approvals->first(static fn (CourseApproval $a): bool => $a->state_code === $state && $a->status === 'approved');
    }

    /**
     * Whether REOS can submit for the school: live, simulated outside production, or not yet.
     *
     * @return 'available'|'simulated'|'unavailable'
     */
    private function reosMode(): string
    {
        $definition = $this->catalogue->find(self::INTEGRATION);
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();

        return match (true) {
            $mode === IntegrationMode::Simulator && ! $this->app->isProduction() => 'simulated',
            $mode === IntegrationMode::Live, $mode === IntegrationMode::Sandbox => 'available',
            default => 'unavailable',
        };
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
