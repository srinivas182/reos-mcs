<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The Eloquent half of tenant isolation (ADR 0015): queries only see the
 * current tenant's rows, the same rule row-level security enforces in
 * PostgreSQL. Models with personal rows (no organisation) implement
 * HasPersonalRows.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', static function (Builder $query): void {
            $tenancy = app(Tenancy::class);

            if ($tenancy->isPlatform()) {
                return;
            }

            $column = $query->getModel()->qualifyColumn(static::tenantColumn());
            $personal = self::hasPersonalRows($query->getModel());
            $tenant = $tenancy->tenant();

            $query->where(static function (Builder $inner) use ($column, $personal, $tenant): void {
                $tenant === null ? $inner->whereRaw('1 = 0') : $inner->where($column, $tenant);

                if ($personal) {
                    $inner->orWhereNull($column);
                }
            });
        });
    }

    /** Written for any model, so a single user of the trait doesn't decide the answer. */
    private static function hasPersonalRows(Model $model): bool
    {
        return $model instanceof HasPersonalRows;
    }

    public static function tenantColumn(): string
    {
        return 'organisation_id';
    }
}
