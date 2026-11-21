<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Enums\OrganisationType;

/**
 * The roles from the journey documents, plus the platform's super admin.
 * A person can hold several, each in an organisation.
 */
enum Role: string
{
    case Candidate = 'candidate';
    case Agent = 'agent';
    case Broker = 'broker';
    case Mentor = 'mentor';
    case Instructor = 'instructor';
    case SchoolAdmin = 'school_admin';
    case Provider = 'provider';
    case AssociationAdmin = 'association_admin';
    case SuperAdmin = 'super_admin';

    /** Admin roles can't be used until the person has set up two-step. */
    public function isAdmin(): bool
    {
        return in_array($this, [self::Broker, self::SchoolAdmin, self::AssociationAdmin, self::SuperAdmin], true);
    }

    /**
     * Order on screens, and which role someone with no choice yet starts in:
     * the one with most responsibility (a broker who is also an agent
     * starts as the broker).
     */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 0,
            self::AssociationAdmin => 1,
            self::SchoolAdmin => 2,
            self::Broker => 3,
            self::Instructor => 4,
            self::Mentor => 5,
            self::Provider => 6,
            self::Agent => 7,
            self::Candidate => 8,
        };
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Candidate, self::Agent => [Permission::LearningStudy],
            self::Broker => [Permission::LearningStudy, Permission::AgentsManage, Permission::OrganisationManage],
            self::Mentor => [Permission::MenteesSupport],
            self::Instructor => [Permission::LearningTeach],
            self::SchoolAdmin => [Permission::LearningTeach, Permission::CoursesManage, Permission::OrganisationManage],
            self::Provider => [Permission::ServicesOffer],
            self::AssociationAdmin => [Permission::MembersManage, Permission::OrganisationManage],
            self::SuperAdmin => Permission::cases(),
        };
    }

    /**
     * Where this role can be held. Platform means "on their own", for
     * people who joined without a brokerage, school or business.
     *
     * @return list<OrganisationType>
     */
    public function organisationTypes(): array
    {
        return match ($this) {
            self::Candidate => [OrganisationType::School, OrganisationType::Platform],
            self::Agent => [OrganisationType::Brokerage, OrganisationType::Platform],
            self::Broker => [OrganisationType::Brokerage],
            self::Mentor => [OrganisationType::Brokerage, OrganisationType::School],
            self::Instructor, self::SchoolAdmin => [OrganisationType::School],
            self::Provider => [OrganisationType::ProviderBusiness, OrganisationType::Platform],
            self::AssociationAdmin => [OrganisationType::Association],
            self::SuperAdmin => [OrganisationType::Platform],
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
