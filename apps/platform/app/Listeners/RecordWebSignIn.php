<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Identity\WebDevice;
use App\Models\User;
use App\Services\Identity\SignInRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Every web sign-in (password, two-step, passkey, Google, Apple, phone code)
 * goes through the same device, email, deletion and audit handling as the app.
 */
final readonly class RecordWebSignIn
{
    /** Set by sign-in controllers that aren't Fortify's own. */
    public const METHOD_ATTRIBUTE = 'reos.sign_in_method';

    public const SIGNED_IN_AT = 'reos.signed_in_at';

    public function __construct(private Request $request, private SignInRecorder $recorder) {}

    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $method = $this->request->attributes->get(self::METHOD_ATTRIBUTE);
        $method = is_string($method) ? $method : match ($this->request->route()?->getName()) {
            'two-factor.login.store' => 'two_factor',
            'passkey.login' => 'passkey',
            default => 'password',
        };

        // When this browser signed in (EnsureAccountActive compares it with a deletion request).
        $this->request->session()->put(self::SIGNED_IN_AT, now()->getTimestamp());

        $this->recorder->record($event->user, WebDevice::from($this->request), $method, $event->user->wasRecentlyCreated);
    }
}
