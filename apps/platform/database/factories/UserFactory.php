<?php

namespace Database\Factories;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            /* @chisel-2fa */
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            /* @end-chisel-2fa */
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A platform super administrator, with two-step set up (admin roles
     * need it). Use withRole() for one without.
     */
    public function superAdmin(): static
    {
        return $this->withTwoFactor()->withRole(Role::SuperAdmin);
    }

    /**
     * Holds a role, in the given organisation or one of the right kind.
     */
    public function withRole(Role $role, ?Organisation $organisation = null): static
    {
        return $this->afterCreating(function (User $user) use ($role, $organisation): void {
            $organisation ??= $role->organisationTypes()[0] === OrganisationType::Platform
                ? Organisation::platform()
                : Organisation::factory()->ofType($role->organisationTypes()[0])->create();

            app(Memberships::class)->attach($user, $role, $organisation);
        });
    }

    /* @chisel-2fa */
    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
    /* @end-chisel-2fa */
}
