import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { toast } from 'sonner';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { calendarDay, calendarEvent } from '@/components/calendar-fixtures';
import { TodayCard } from '@/components/today-card';
import { i18n } from '@/lib/i18n';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router,
}));
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-21T15:20:00Z')); // 9:20 AM in Houston
});
afterEach(async () => {
    vi.useRealTimers();
    vi.clearAllMocks();
    await i18n.changeLanguage('en');
});

describe('Today on the agent dashboard (web journey screen 12)', () => {
    it('lists the day with where each item came from and when', () => {
        render(<TodayCard today={calendarDay()} />);

        const rows = within(
            screen.getByRole('table', { name: "Today's calendar" }),
        ).getAllByRole('row');
        expect(rows.map((row) => row.textContent)).toEqual([
            'TypeWhatFromWhen',
            'Option period ends — 1234 Oak Ridge DrGoogle CalendarAll day',
            'Call back Maria Lopez — pre-approvalGoogle Calendar10:00 AM',
            'Showing — 1234 Oak Ridge DrGoogle Calendar2:00 PM',
            'BusyGoogle Calendar6:00 PM',
        ]);
        expect(
            screen.getByText(
                'Goes to linda.park@lonestarrealty.example in Google Calendar.',
            ),
        ).toBeInTheDocument();
    });

    it("adds an appointment in the person's time zone, without emailing guests", async () => {
        const user = userEvent.setup();
        render(<TodayCard today={calendarDay()} />);

        await user.click(
            screen.getAllByRole('button', { name: 'New appointment' })[0]!,
        );
        const dialog = within(screen.getByRole('dialog'));
        await user.type(
            dialog.getByLabelText('What'),
            'Buyer consultation — Ana Torres',
        );
        await user.type(
            dialog.getByLabelText('Guests'),
            'ana.torres@example.com',
        );
        expect(
            dialog.getByRole('switch', {
                name: 'Email the guests an invitation',
            }),
        ).toHaveAttribute('aria-checked', 'false');
        await user.click(dialog.getByRole('button', { name: 'Add' }));

        expect(router.post).toHaveBeenCalledWith(
            '/calendar/events',
            {
                title: 'Buyer consultation — Ana Torres',
                kind: 'appointment',
                all_day: false,
                // 10:00–11:00 AM in Houston.
                starts_at: '2026-11-21T16:00:00.000Z',
                ends_at: '2026-11-21T17:00:00.000Z',
                location: null,
                description: null,
                attendees: ['ana.torres@example.com'],
                notify_guests: false,
            },
            expect.anything(),
        );
    });

    it('opens an item; deleting one REOS made offers Undo instead of asking', async () => {
        const user = userEvent.setup();
        router.delete.mockImplementation(
            (_url: string, options: { onSuccess: () => void }) =>
                options.onSuccess(),
        );
        render(<TodayCard today={calendarDay()} />);

        await user.click(
            screen.getByRole('button', { name: 'Showing — 1234 Oak Ridge Dr' }),
        );
        const dialog = within(screen.getByRole('dialog'));
        expect(dialog.getByLabelText('What')).toHaveValue(
            'Showing — 1234 Oak Ridge Dr',
        );
        expect(dialog.getByLabelText('Starts')).toHaveValue('14:00');
        expect(
            dialog.getByRole('link', { name: 'Open in Google Calendar' }),
        ).toHaveAttribute(
            'href',
            'https://calendar.google.com/calendar/event?eid=demo',
        );
        await user.click(dialog.getByRole('button', { name: 'Delete' }));

        expect(router.delete).toHaveBeenCalledWith(
            '/calendar/events/41',
            expect.anything(),
        );
        expect(toast).toHaveBeenCalledWith(
            'Deleted Showing — 1234 Oak Ridge Dr.',
            expect.objectContaining({
                action: expect.objectContaining({ label: 'Undo' }),
            }),
        );
    });

    it("shows a calendar shared as busy only without details, and a tool's event without Delete", async () => {
        const user = userEvent.setup();
        render(<TodayCard today={calendarDay()} />);

        await user.click(screen.getByRole('button', { name: 'Busy' }));
        expect(
            screen.getByText(
                'From a calendar shared as busy only, so only the time is shown.',
            ),
        ).toBeInTheDocument();
        await user.keyboard('{Escape}');

        await user.click(
            screen.getByRole('button', {
                name: 'Call back Maria Lopez — pre-approval',
            }),
        );
        const dialog = within(screen.getByRole('dialog'));
        expect(dialog.queryByRole('button', { name: 'Delete' })).toBeNull();
        expect(dialog.queryByLabelText('Type')).toBeNull();
        expect(
            dialog.getByText(
                'Made in Google Calendar: delete it there if you need to.',
            ),
        ).toBeInTheDocument();
    });

    it('says when an item changed in two places, and Undo uses the other version', async () => {
        const user = userEvent.setup();
        const conflicted = calendarEvent({
            conflict: {
                id: 9,
                kept: 'reos',
                reason: 'edited_both',
                losing: {
                    title: 'Showing — 1234 Oak Ridge Dr (Garcias running late)',
                    starts_at: '2026-11-21T20:00:00+00:00',
                    ends_at: '2026-11-21T20:45:00+00:00',
                    all_day: false,
                    location: null,
                    deleted: false,
                },
                at: '2026-11-21T15:00:00+00:00',
                until: '2026-12-21T15:00:00+00:00',
            },
        });
        render(<TodayCard today={calendarDay({ data: [conflicted] })} />);

        expect(
            screen.getByText(
                'Changed in Google Calendar too. Your REOS change was newer, so it was kept.',
            ),
        ).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', {
                name: /^Showing — 1234 Oak Ridge Dr/,
            }),
        );
        const dialog = within(screen.getByRole('dialog'));
        expect(
            dialog.getByText(
                'The other version: Showing — 1234 Oak Ridge Dr (Garcias running late), Sat, Nov 21, 2:00 PM.',
            ),
        ).toBeInTheDocument();
        expect(
            dialog.getByText('You can undo this until Dec 21.'),
        ).toBeInTheDocument();
        await user.click(
            dialog.getByRole('button', { name: 'Use the other version' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/calendar/conflicts/9/undo',
            {},
            expect.anything(),
        );
    });

    it('flags an item not yet saved to the tool, with Try again', async () => {
        const user = userEvent.setup();
        render(
            <TodayCard
                today={calendarDay({
                    data: [calendarEvent({ sync: 'failed' })],
                })}
            />,
        );

        expect(
            screen.getByText('Not saved to Google Calendar yet.'),
        ).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', {
                name: /^Showing — 1234 Oak Ridge Dr/,
            }),
        );
        await user.click(screen.getByRole('button', { name: 'Try again' }));
        expect(router.post).toHaveBeenCalledWith(
            '/calendar/events/41/retry',
            {},
            expect.anything(),
        );
    });

    it('teaches what to do on an empty day, and brings back an event deleted in the tool', async () => {
        const user = userEvent.setup();
        render(
            <TodayCard
                today={calendarDay({
                    data: [],
                    write_to: null,
                    removed: [
                        {
                            conflict: 12,
                            title: 'Inspection — 405 Westheimer Rd',
                            starts_at: '2026-11-23T19:00:00+00:00',
                            at: '2026-11-21T14:00:00+00:00',
                        },
                    ],
                })}
            />,
        );

        expect(
            screen.getByRole('region', {
                name: 'Nothing on your calendar today',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Connect a calendar' }),
        ).toHaveAttribute('href', '/connections');
        expect(
            screen.getByText(
                'Stays in REOS. Choose a calendar in Connections to add it there too.',
            ),
        ).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Bring it back' }));
        expect(router.post).toHaveBeenCalledWith(
            '/calendar/conflicts/12/undo',
            {},
            expect.anything(),
        );
    });

    it('speaks Spanish', async () => {
        await i18n.changeLanguage('es');
        render(<TodayCard today={calendarDay()} />);

        expect(screen.getByText('Hoy')).toBeInTheDocument();
        expect(screen.getAllByText('Todo el día')).toHaveLength(1);
        expect(screen.getByText('Ocupado')).toBeInTheDocument();
    });
});
