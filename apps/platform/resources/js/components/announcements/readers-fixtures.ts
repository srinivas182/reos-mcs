import type { Readers } from './readers-sheet';

/** Linda's "Office closed Friday": James has read it, Maria hasn't. */
export const officeClosed: Readers = {
    id: '01936b7a-0000-7000-8000-000000000401',
    title: 'Office closed Friday',
    recipients: 2,
    read: 1,
    reminded_at: null,
    can_remind: true,
    people: [
        { name: 'Maria Garcia', read_at: null },
        { name: 'James Miller', read_at: '2026-11-23T10:12:00-06:00' },
    ],
};

export const officeClosedReminded: Readers = {
    ...officeClosed,
    reminded_at: '2026-11-23T11:00:00-06:00',
    can_remind: false,
};
