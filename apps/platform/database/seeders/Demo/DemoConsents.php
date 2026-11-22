<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\ConsentRecord;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\Services\Privacy\Consents;

/**
 * Consent records as on Settings › Privacy (A6.3): every persona agreed to
 * the terms and privacy notice when they signed up; James Miller also
 * agreed to texts in the app.
 */
final readonly class DemoConsents
{
    public function __construct(private Consents $consents) {}

    public function seed(): void
    {
        User::query()->whereNotNull('email')->where('email', 'like', '%@demo.reos.test')->each(function (User $user): void {
            if (! ConsentRecord::query()->where('user_id', $user->id)->exists()) {
                $this->consents->signedUp($user);
            }
        });

        $james = User::query()->where('email', 'james.miller@demo.reos.test')->first();

        if ($james !== null && $this->consents->latest($james, ConsentPurpose::Sms) === null) {
            $this->consents->grant($james, ConsentPurpose::Sms, ConsentMethod::App);
        }
    }
}
