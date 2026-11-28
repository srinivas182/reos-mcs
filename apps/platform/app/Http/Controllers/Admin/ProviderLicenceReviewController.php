<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectLicenceRequest;
use App\Licences\ProviderLicences;
use App\Models\ProviderLicence;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provider licence reviews (B10.3): NMLS IDs (checked on NMLS Consumer
 * Access), TREC or TDI numbers with no record or another name, and photo
 * IDs for categories without a licence (B15.2).
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

    /** The photo ID behind a business_id check (B15.2), for the reviewer only. */
    public function document(ProviderLicence $licence, #[CurrentUser] User $reviewer, AuditLogger $audit): StreamedResponse
    {
        abort_unless($licence->kind === 'business_id' && $licence->document_path !== null, 404);
        $audit->record('provider_licence.document_viewed', 'provider_licence', (string) $licence->id, ['person' => $licence->user_id], $reviewer);

        return Storage::disk(config()->string('reos.content.disk'))->response($licence->document_path, null, ['Cache-Control' => 'private, no-store']);
    }
}
