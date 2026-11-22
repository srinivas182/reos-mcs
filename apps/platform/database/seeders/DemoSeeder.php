<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Database\Seeders\Demo\DemoBilling;
use Database\Seeders\Demo\DemoCatalogue;
use Database\Seeders\Demo\DemoConnections;
use Database\Seeders\Demo\DemoContent;
use Database\Seeders\Demo\DemoGateways;
use Database\Seeders\Demo\DemoLicences;
use Database\Seeders\Demo\DemoStates;
use Database\Seeders\Demo\DemoTenants;
use Database\Seeders\Demo\Personas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Creates one verified login per role from the journey personas, with
 * their roles in their organisations.
 *
 * Safe to run repeatedly (it updates in place) and refuses to run in
 * production, because every demo account shares a known password.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo personas must never be seeded in production.');
        }

        $password = Hash::make(config()->string('reos.demo_password'));
        $memberships = app(Memberships::class);

        foreach (Personas::all() as $persona) {
            $user = User::query()->updateOrCreate(
                ['email' => $persona->email],
                [
                    'name' => $persona->name,
                    'password' => $password,
                    'email_verified_at' => now(),
                ],
            );

            $organisation = $this->organisation($persona->organisation);
            $held = $memberships->of($user);

            foreach ($persona->roles() as $name) {
                $role = Role::from($name);

                if (! collect($held)->contains(static fn (Membership $m): bool => $m->is($role, $organisation->id))) {
                    $memberships->attach($user, $role, $organisation);
                }
            }
        }

        // Demo connections on the Simulator (B3.1): one syncing, one needing a reconnect.
        app(DemoConnections::class)->seed();
        // "Notify me" demand and a tool request (B3.2).
        app(DemoCatalogue::class)->seed();

        // Tenant details for the Tenants screens (A4.1b).
        app(DemoTenants::class)->seed();
        // States being prepared and waited for (screen 61), then licences (A5.1).
        app(DemoStates::class)->seed();
        // State licences, as on "My licences" (A5.1).
        app(DemoLicences::class)->seed();
        // REOS courses and the knowledge base, as on screen 63 (A5.3).
        app(DemoContent::class)->seed();
        // Plans, prices and a sponsor discount, as on screens 67 and 3 (B5.1).
        app(DemoBilling::class)->seed();
        app(DemoGateways::class)->seed();
    }

    private function organisation(string $name): Organisation
    {
        $type = Personas::organisations()[$name];

        if ($type === OrganisationType::Platform) {
            return Organisation::platform();
        }

        return Organisation::query()->firstOrCreate(['name' => $name, 'type' => $type]);
    }
}
