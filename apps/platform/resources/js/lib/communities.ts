import type { CommunityRoutes } from '@/components/communities/types';
import { code as newCode } from '@/routes/community';
import { destroy as postDestroy, pin } from '@/routes/community-posts';
import {
    store as resourceStore,
    destroy as resourceDestroy,
} from '@/routes/community/resources';
import {
    attendance,
    destroy as sessionDestroy,
    roster,
    store as sessionStore,
} from '@/routes/community/sessions';
import { rsvp } from '@/routes/community-sessions';
import { store as postStore } from '@/routes/communities/posts';

/** Where a community's actions go on the web (B17.2). */
export function communityRoutes(community: string): CommunityRoutes {
    return {
        post: postStore(community).url,
        removePost: (id) => postDestroy(id).url,
        pin: (id) => pin(id).url,
        rsvp: (id) => rsvp(id).url,
        addResource: resourceStore().url,
        removeResource: (id) => resourceDestroy(id).url,
        schedule: sessionStore().url,
        cancelSession: (id) => sessionDestroy(id).url,
        roster: (id) => roster(id).url,
        attendance: (id) => attendance(id).url,
        newCode: newCode().url,
    };
}
