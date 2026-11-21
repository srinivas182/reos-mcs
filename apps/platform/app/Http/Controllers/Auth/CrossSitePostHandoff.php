<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Takes a sign-in answer that an identity provider posts from its own site
 * (Apple's form_post, a SAML response) and continues with a same-site GET.
 *
 * A cross-site POST doesn't carry the (SameSite=Lax) session cookie. Run
 * with the session, Laravel would start a new one and send its cookie back,
 * replacing the person's session and losing the sign-in's state. So this
 * route runs without the session (routes/handoff.php): it parks the answer
 * for five minutes under a random key and redirects. The GET, which carries
 * the session, checks the answer against the state saved there.
 */
final class CrossSitePostHandoff extends Controller
{
    public const CACHE_PREFIX = 'reos:post-handoff:';

    /** What each provider posts; anything else is dropped. */
    private const FIELDS = ['state', 'code', 'user', 'error', 'demo_email', 'SAMLResponse', 'RelayState'];

    /** Larger than any real answer (a signed SAML response is a few kilobytes). */
    private const MAX_FIELD = 200_000;

    public function __invoke(Request $request): RedirectResponse
    {
        $answer = array_filter(
            $request->only(self::FIELDS),
            static fn (mixed $value): bool => is_string($value) && strlen($value) <= self::MAX_FIELD,
        );

        $key = Str::random(40);
        Cache::put(self::CACHE_PREFIX.$key, $answer, 300);

        return redirect()->to($request->url().'?'.http_build_query(['handoff' => $key]), 303);
    }

    /** On the GET: the parked answer, merged into the request (once). */
    public static function restore(Request $request): void
    {
        if ($request->filled('handoff')) {
            $answer = Cache::pull(self::CACHE_PREFIX.$request->string('handoff')->toString());
            $request->merge(is_array($answer) ? $answer : []);
        }
    }
}
