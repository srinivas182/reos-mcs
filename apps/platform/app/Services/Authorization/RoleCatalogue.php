<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Authorization\Permission;
use App\Authorization\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes the roles and permissions defined in code (Role, Permission) to
 * Spatie's tables. Safe to run on every deploy; roles and permissions that
 * customers or plugins add themselves are left alone.
 */
final readonly class RoleCatalogue
{
    public const GUARD = 'web';

    public function __construct(private PermissionRegistrar $registrar) {}

    public function sync(): void
    {
        foreach (Permission::cases() as $permission) {
            PermissionModel::query()->firstOrCreate(['name' => $permission->value, 'guard_name' => self::GUARD]);
        }

        foreach (Role::cases() as $role) {
            // Catalogue roles are global (no organisation): any organisation can use them.
            $model = RoleModel::query()->firstOrCreate([
                'name' => $role->value,
                'guard_name' => self::GUARD,
                'organisation_id' => null,
            ]);

            $model->syncPermissions(array_map(static fn (Permission $permission): string => $permission->value, $role->permissions()));
        }

        $this->registrar->forgetCachedPermissions();
    }
}
