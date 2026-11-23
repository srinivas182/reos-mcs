<?php

declare(strict_types=1);

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\ReportShare;
use Inertia\Inertia;
use Inertia\Response;

/** A weekly report shared by link with a study buddy (A9.2e): first name only, for 14 days. */
final class SharedReportController extends Controller
{
    public function show(string $share): Response
    {
        $found = ReportShare::query()->where('uuid', $share)->whereNull('organisation_id')->whereNull('revoked_at')->where('expires_at', '>', now())->firstOrFail();

        return Inertia::render('public/report', ['report' => $found->report]);
    }
}
