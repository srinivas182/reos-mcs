<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\Permission;
use App\Models\AuditEvent;
use App\Models\User;

/** The audit log is read-only and for `audit.view`. */
final class AuditEventPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AuditView->value);
    }

    public function view(User $actor, AuditEvent $event): bool
    {
        return $actor->can(Permission::AuditView->value);
    }
}
