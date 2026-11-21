import type { Membership } from '@/types/auth';

/** A person on the People screen (super admin). */
export type Person = {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    joined_at: string | null;
    two_step: boolean;
    roles: Membership[];
};

/** A role change in a person's "Recent changes". */
export type RoleChange = {
    id: number;
    /** `role.granted` or `role.removed`. */
    action: string;
    actor: string | null;
    properties: { role?: string; organisation_id?: number } | null;
    created_at: string;
};
