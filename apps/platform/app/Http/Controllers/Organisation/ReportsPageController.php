<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Brokerage\Reports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\ReportPeriodRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Brokerage › Reports on the web (A16.3): closings matched to the transaction tool, and training. */
final class ReportsPageController extends Controller
{
    public function __invoke(ReportPeriodRequest $request, #[CurrentUser] User $user, Reports $reports): Response
    {
        return Inertia::render('organisation/reports', ['closings' => $reports->closings($user, $request->period()), 'training' => $reports->training($user)]);
    }
}
