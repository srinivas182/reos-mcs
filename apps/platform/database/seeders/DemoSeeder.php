<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Demo\Personas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Creates one verified login per role from the journey personas.
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

        foreach (Personas::all() as $persona) {
            $user = User::query()->updateOrCreate(
                ['email' => $persona->email],
                [
                    'name' => $persona->name,
                    'password' => $password,
                    'email_verified_at' => now(),
                ],
            );

            // Not mass assignable on purpose; Sprint 3 replaces this with roles.
            $user->forceFill(['is_super_admin' => $persona->role === 'super_admin'])->save();
        }
    }
}
