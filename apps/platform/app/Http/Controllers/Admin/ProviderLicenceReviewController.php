<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectLicenceRequest;
use App\Licences\ProviderLicences;
use App\Models\ProviderLicence;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Provider licence reviews (B10.3): NMLS IDs (checked on NMLS Consumer
 * Access), and TREC or TDI numbers with no record or another name.
 */
final class ProviderLicenceReviewController extends Controller
{
    public function approve(ProviderLicence $licence, #[CurrentUser] User $reviewer, ProviderLicences $licences): RedirectResponse
    {
        abort_unless($licence->status === ProviderLicence::REVIEW, 409);
        $licences->approve($licence, $reviewer);

        return back();
    }

    public function reject(RejectLicenceRequest $request, ProviderLicence $licence, #[CurrentUser] User $reviewer, ProviderLicences $licences): RedirectResponse
    {
        abort_unless($licence->status === ProviderLicence::REVIEW, 409);
        $licences->reject($licence, $reviewer, $request->note());

        return back();
    }
}
