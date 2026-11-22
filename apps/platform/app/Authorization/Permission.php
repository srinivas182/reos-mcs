<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Everything a role can be allowed to do, named `area.action`.
 *
 * Check them with `$user->can(Permission::X->value)`: the answer comes from
 * the person's active role (see ActiveRole), not from every role they hold.
 * Later sprints add permissions here and to the roles in Role::permissions();
 * `php artisan reos:roles:sync` (run by deploys) writes them to the database.
 */
enum Permission: string
{
    // Learning
    case LearningStudy = 'learning.study';
    case LearningTeach = 'learning.teach';
    case CoursesManage = 'courses.manage';

    // Mentoring
    case MenteesSupport = 'mentees.support';

    // Brokerage
    case AgentsManage = 'agents.manage';

    // Services
    case ServicesOffer = 'services.offer';

    // Association
    case MembersManage = 'members.manage';

    // Organisation
    case OrganisationManage = 'organisation.manage';

    // Platform
    case PeopleManage = 'people.manage';
    case TenantsManage = 'tenants.manage';
    case IntegrationsManage = 'integrations.manage';
    case StatesManage = 'states.manage';
    case ContentManage = 'content.manage';
    case AuditView = 'audit.view';

    /** The area a permission is grouped under on screens. */
    public function area(): string
    {
        return strstr($this->value, '.', true) ?: $this->value;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
