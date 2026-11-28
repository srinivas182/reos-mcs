<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Locale;
use App\Leads\Invites;
use App\Models\User;
use App\Services\Privacy\Consents;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            // "I agree to the Terms and the Privacy notice" (A6.3).
            'terms' => ['accepted'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            // Keep the language they were already using on the site.
            'locale' => Locale::current(),
        ]);
        app(Consents::class)->signedUp($user);
        // Joined through someone's invite link (B14.3b): remember who invited them.
        $code = request()->hasSession() ? request()->session()->pull('invite') : null;
        app(Invites::class)->claim($user, is_string($code) ? $code : null);

        return $user;
    }
}
