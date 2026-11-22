<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Features\Releases;
use App\Models\Release;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/** Dismisses "What's new" on the web (A6.2); it doesn't show again on the web or the phone. */
final class WhatsNewController extends Controller
{
    public function dismiss(Release $release, Releases $releases, #[CurrentUser] User $user): RedirectResponse
    {
        abort_if($release->published_at === null, 404);
        $releases->dismiss($release, $user);

        return back();
    }
}
