<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Reference data (roles, Texas licence rules, course catalogue) is added
     * here from Sprint 2. Demo logins are seeded only outside production.
     */
    public function run(): void
    {
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
