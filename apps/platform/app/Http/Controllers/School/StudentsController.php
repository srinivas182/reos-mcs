<?php

declare(strict_types=1);

namespace App\Http\Controllers\School;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Models\ReportShare;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Students (school admin web; A9.2e): the weekly progress reports
 * candidates have shared with the school, newest first. A school sees a
 * candidate's report only once the candidate shares it.
 */
final class StudentsController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $roles): Response
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && $membership->role === Role::SchoolAdmin && $membership->organisation->type === OrganisationType::School, 404);

        $shares = ReportShare::query()->where('organisation_id', $membership->organisation->id)->whereNull('revoked_at')->with('user:id,name')->latest('week_start')->latest('id')->limit(200)->get();

        return Inertia::render('school/students', [
            'reports' => $shares->map(static fn (ReportShare $s): array => [
                'id' => $s->id, 'name' => (string) $s->user->name, 'week_start' => $s->week_start->toDateString(), 'report' => $s->report, 'shared_at' => $s->updated_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }
}
