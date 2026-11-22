<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\User;
use App\Notifications\Centre\Sms\TextAlerts;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use Illuminate\Validation\ValidationException;

/**
 * What a person may change themselves (A6.3), for the web settings and the
 * app: switches on or off; consents for one thing (a session, a provider)
 * withdrawn but never granted here (those are asked where they happen);
 * the terms and privacy notice only by deleting the account.
 */
final readonly class ConsentChoices
{
    public function __construct(private Consents $consents, private TextAlerts $texts) {}

    public function change(User $user, ConsentPurpose $purpose, bool $granted, ?string $subject, ConsentMethod $method): void
    {
        if ($purpose->required()) {
            throw ValidationException::withMessages(['purpose' => __('consents.errors.required')]);
        }

        if ($purpose->perSubject() && ($subject === null || $granted)) {
            throw ValidationException::withMessages(['subject' => __('consents.errors.askedWhereUsed')]);
        }

        if (! $purpose->perSubject() && $subject !== null) {
            throw ValidationException::withMessages(['subject' => __('consents.errors.noSubject')]);
        }

        // Texts need a verified number and send the confirmation text (B6.2c).
        if ($purpose === ConsentPurpose::Sms) {
            $this->texts->turn($user, $granted, $method);

            return;
        }

        // Nothing to record when nothing changes.
        if ($this->consents->allows($user, $purpose, $subject) === $granted && $this->consents->latest($user, $purpose, $subject) !== null) {
            return;
        }

        $granted
            ? $this->consents->grant($user, $purpose, $method, $subject)
            : $this->consents->withdraw($user, $purpose, $method, $subject);
    }
}
