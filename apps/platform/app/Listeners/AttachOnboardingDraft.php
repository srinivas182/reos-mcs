<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Onboarding\WebDraft;
use Illuminate\Auth\Events\Login;

/**
 * The website's free-quiz answers, path and state follow the person into
 * their account, whichever way they sign in or sign up.
 */
final readonly class AttachOnboardingDraft
{
    public function __construct(private WebDraft $draft) {}

    public function handle(Login $event): void
    {
        if ($event->guard === 'web' && $event->user instanceof User) {
            $this->draft->attach($event->user);
        }
    }
}
