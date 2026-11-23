<?php

declare(strict_types=1);

namespace App\Http\Controllers\Deals;

use App\Deals\DealAccess;
use App\Deals\Signatures;
use App\Http\Controllers\Controller;
use App\Models\DealFile;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\Content\LessonFiles;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The web side of signatures (B9.3): back from DocuSign's sender view, and a deal's kept files. */
final class SignaturePageController extends Controller
{
    /** DocuSign sends the agent back here with `event` (Send, Save, Cancel and others); the deal opens again with what happened. */
    public function returned(Request $request, SignatureRequest $signature, #[CurrentUser] User $user, DealAccess $access, Signatures $signatures): RedirectResponse
    {
        $access->allow($user);
        abort_unless($signature->user_id === $user->id, 404);
        $sent = strtolower($request->string('event')->toString()) === 'send';
        $signature = rescue(fn () => $signatures->refresh($signature), $signature, report: true);
        $deal = $signature->deal;

        return redirect('/deals?deal='.$deal->uuid)->with('success', __($sent || $signature->status !== 'draft' ? 'deals.signatures.sent' : 'deals.signatures.saved'));
    }

    /** A deal's kept file, once scanned clean, on the local disk, through its 30-minute signed link. Object storage signs its own. */
    public function file(DealFile $file): BinaryFileResponse
    {
        $disk = LessonFiles::disk();
        abort_unless($file->scan_status === 'clean' && $disk->exists($file->path), 404);

        return response()->file($disk->path($file->path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addcslashes($file->name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
