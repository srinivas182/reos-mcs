import { dashboard } from '@/routes';
import { index as connections } from '@/routes/connections';
import { index as contacts } from '@/routes/contacts';
import { index as deals } from '@/routes/deals';
import { index as listings } from '@/routes/deals/listings';
import { index as learn } from '@/routes/learn';
import { index as mentors } from '@/routes/mentors';
import { index as rolePlay } from '@/routes/role-play';

/**
 * Where a plan task or day-one step is done on the web (A11.1b). The
 * license announcement lives with the agent page on My listings; the
 * calendar is on the dashboard.
 */
export function planHref(link: string | null): string | null {
    switch (link) {
        case 'connections':
            return connections().url;
        case 'contacts':
            return contacts().url;
        case 'deals':
            return deals().url;
        case 'listings':
        case 'announce':
            return listings().url;
        case 'learn':
            return learn().url;
        case 'mentors':
            return mentors().url;
        case 'role_play':
            return rolePlay().url;
        case 'calendar':
            return dashboard().url;
        default:
            return null;
    }
}
