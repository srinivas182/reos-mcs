<?php

declare(strict_types=1);

namespace App\Http\Controllers\School;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\CeReportRequest;
use App\Licences\Ce\CeReporting;
use App\Models\CeReport;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * CE reporting to TREC (A11.3, ADR 0045; school admin web screen 51):
 * Ready, Rejected and the reports sent, with Submit (TREC's file or REOS
 * submits), the file to download, TREC's reference and rejections.
 */
final class CeReportsController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $roles, CeReporting $reporting): Response
    {
        return Inertia::render('school/ce-reports', ['reporting' => $reporting->overview($this->school($user, $roles))]);
    }

    public function store(CeReportRequest $request, #[CurrentUser] User $user, ActiveRole $roles, CeReporting $reporting): JsonResponse
    {
        $school = $this->school($user, $roles);
        $report = $reporting->create($school, $user, $request->string('from_on')->toString(), $request->string('to_on')->toString(), $request->string('method')->toString());

        return response()->json(['data' => ['id' => $report->uuid, 'status' => $report->status, 'reference' => $report->reference, 'overview' => $reporting->overview($school)]], 201);
    }

    /** TREC's upload file (CSV). */
    public function file(string $report, #[CurrentUser] User $user, ActiveRole $roles, CeReporting $reporting): HttpResponse
    {
        $found = $this->report($report, $user, $roles);

        return response($reporting->csv($found), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="trec-ce-'.substr($found->from_on, 0, 10).'-to-'.substr($found->to_on, 0, 10).'.csv"',
        ]);
    }

    public function reference(Request $request, string $report, #[CurrentUser] User $user, ActiveRole $roles, CeReporting $reporting): JsonResponse
    {
        $reference = $request->validate(['reference' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[A-Za-z0-9\-\/ .]+$/']])['reference'];
        $found = $this->report($report, $user, $roles);
        $reporting->confirm($found, is_string($reference) ? trim($reference) : '', $user);

        return response()->json(['data' => $reporting->overview($this->school($user, $roles))]);
    }

    public function line(Request $request, string $report, int $completion, #[CurrentUser] User $user, ActiveRole $roles, CeReporting $reporting): JsonResponse
    {
        $data = $request->validate(['rejected' => ['required', 'boolean'], 'reason' => ['required_if:rejected,true', 'nullable', 'string', 'max:300']]);
        $reporting->reject($this->report($report, $user, $roles), $completion, (bool) $data['rejected'], is_string($data['reason'] ?? null) ? $data['reason'] : null, $user);

        return response()->json(['data' => $reporting->overview($this->school($user, $roles))]);
    }

    private function report(string $uuid, User $user, ActiveRole $roles): CeReport
    {
        return CeReport::query()->where('organisation_id', $this->school($user, $roles)->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function school(User $user, ActiveRole $roles): Organisation
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && $membership->role === Role::SchoolAdmin && $membership->organisation->type === OrganisationType::School, 404);

        return $membership->organisation;
    }
}
