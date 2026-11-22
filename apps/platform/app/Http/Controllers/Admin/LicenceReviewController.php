<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LicenceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectLicenceRequest;
use App\Models\Licence;
use App\Models\User;
use App\Services\Licences\Licences;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Licence reviews (A5.1): no licence counts without a regulator match or a
 * reviewer's approval. Reviewers approve or reject from the person's page.
 */
final class LicenceReviewController extends Controller
{
    public function approve(Licence $licence, #[CurrentUser] User $reviewer, Licences $licences): RedirectResponse
    {
        abort_unless($licence->status === LicenceStatus::PendingReview, 409);
        $licences->approve($licence, $reviewer);

        return back();
    }

    public function reject(RejectLicenceRequest $request, Licence $licence, #[CurrentUser] User $reviewer, Licences $licences): RedirectResponse
    {
        abort_unless($licence->status === LicenceStatus::PendingReview, 409);
        $licences->reject($licence, $reviewer, $request->note());

        return back();
    }
}
