<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Identity\SignInFailed;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Google (and Apple on Android) in the phone app: the app opens the web
 * sign-in in the system browser with a PKCE challenge. When the web sign-in
 * finishes, the browser returns to the app with a one-time code (valid two
 * minutes) that only the app holding the matching verifier can turn into a
 * device token. No token ever travels in a link.
 */
final readonly class AppSignInHandoff
{
    public const SESSION = 'reos.app_sign_in';

    private const TTL_SECONDS = 120;

    public function __construct(private Cache $cache) {}

    /** Remembers that this browser sign-in was started by the app. */
    public function begin(Request $request): void
    {
        $challenge = $request->string('app_challenge')->toString();

        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge) === 1) {
            $request->session()->put(self::SESSION, $challenge);
        }
    }

    public function pending(Request $request): bool
    {
        return $request->hasSession() && is_string($request->session()->get(self::SESSION));
    }

    /** Where the browser goes next: back to the app with a one-time code. */
    public function finish(Request $request, User $user, string $method, bool $created): string
    {
        $challenge = $request->session()->pull(self::SESSION);
        $challenge = is_string($challenge) ? $challenge : '';
        $code = Str::random(48);

        $this->cache->put($this->key($code), [
            'user_id' => $user->id,
            'challenge' => $challenge,
            'method' => $method,
            'created' => $created,
        ], self::TTL_SECONDS);

        return $this->appUrl(['code' => $code]);
    }

    /** Back to the app with a reason it can show, when the sign-in failed. */
    public function fail(Request $request, string $reason): string
    {
        $request->session()->forget(self::SESSION);

        return $this->appUrl(['error' => $reason]);
    }

    /**
     * @return array{user: User, method: string, created: bool}
     */
    public function redeem(string $code, string $verifier): array
    {
        $saved = $this->cache->pull($this->key($code));
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        if (! is_array($saved) || ! is_string($saved['challenge'] ?? null) || ! hash_equals($saved['challenge'], $expected)) {
            throw SignInFailed::invalidToken('Unknown, expired or stolen app sign-in code');
        }

        $user = User::query()->find($saved['user_id'] ?? null);

        if (! $user instanceof User) {
            throw SignInFailed::invalidToken('Account no longer exists');
        }

        return [
            'user' => $user,
            'method' => is_string($saved['method'] ?? null) ? $saved['method'] : 'web',
            'created' => ($saved['created'] ?? false) === true,
        ];
    }

    /** @param  array<string, string>  $query */
    private function appUrl(array $query): string
    {
        return config()->string('reos.mobile.redirect').'?'.http_build_query($query);
    }

    private function key(string $code): string
    {
        return 'reos:app-sign-in:'.hash('sha256', $code);
    }
}
