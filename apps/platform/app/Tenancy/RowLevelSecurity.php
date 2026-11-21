<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL row-level security for tenant-owned tables (ADR 0015).
 *
 * A row is visible, and may be written, when the session is in platform
 * scope or the row's organisation is the current tenant. `FORCE` applies the
 * policy to the table's owner too, so only a superuser or a role with
 * BYPASSRLS could skip it, and `reos:tenancy:check` refuses those.
 *
 * Used from migrations; a no-op on SQLite (local tests), where the Eloquent
 * scope still applies.
 */
final class RowLevelSecurity
{
    public const POLICY = 'tenant_isolation';

    /**
     * @param  bool  $allowPersonal  Rows with no organisation (a person's own) stay visible;
     *                               who may see them is decided in the app.
     */
    public static function protect(string $table, string $column = 'organisation_id', bool $allowPersonal = false): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $condition = self::condition($column, $allowPersonal);

        DB::statement("alter table \"{$table}\" enable row level security");
        DB::statement("alter table \"{$table}\" force row level security");
        DB::statement('drop policy if exists '.self::POLICY." on \"{$table}\"");
        DB::statement('create policy '.self::POLICY." on \"{$table}\" using ({$condition}) with check ({$condition})");
    }

    public static function release(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('drop policy if exists '.self::POLICY." on \"{$table}\"");
        DB::statement("alter table \"{$table}\" no force row level security");
        DB::statement("alter table \"{$table}\" disable row level security");
    }

    public static function condition(string $column = 'organisation_id', bool $allowPersonal = false): string
    {
        $parts = [
            "current_setting('".Tenancy::SETTING_SCOPE."', true) = '".Tenancy::SCOPE_PLATFORM."'",
            "\"{$column}\" = nullif(current_setting('".Tenancy::SETTING_TENANT."', true), '')::bigint",
        ];

        if ($allowPersonal) {
            $parts[] = "\"{$column}\" is null";
        }

        return implode(' or ', $parts);
    }
}
