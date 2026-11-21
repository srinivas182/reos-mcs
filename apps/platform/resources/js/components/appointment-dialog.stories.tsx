import type { Meta, StoryObj } from '@storybook/react-vite';
import { AppointmentDialog } from '@/components/appointment-dialog';
import { calendarDay, calendarEvent } from '@/components/calendar-fixtures';

/** An appointment, opened from Today on the agent dashboard (screen 12; B4.1). */
const meta = {
    title: 'Calendar/Appointment',
    component: AppointmentDialog,
    args: { event: 'new', day: calendarDay(), onClose: () => undefined },
} satisfies Meta<typeof AppointmentDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

/** New appointment: goes to Linda's Google Calendar. */
export const New: Story = {};

/** Linda's showing with guests, written to Google. */
export const Showing: Story = {
    args: {
        event: calendarEvent({ attendees: ['garcia.family@example.com'] }),
    },
};

/** Changed in Google after REOS: Google's version was kept; Undo brings REOS's back. */
export const ChangedInGoogle: Story = {
    args: {
        event: calendarEvent({
            title: 'Showing — 1234 Oak Ridge Dr (Garcias running late)',
            conflict: {
                id: 9,
                kept: 'tool',
                reason: 'edited_both',
                losing: {
                    title: 'Showing — 1234 Oak Ridge Dr',
                    starts_at: '2026-11-21T21:00:00+00:00',
                    ends_at: '2026-11-21T21:45:00+00:00',
                    all_day: false,
                    location: null,
                    deleted: false,
                },
                at: '2026-11-21T15:00:00+00:00',
                until: '2026-12-21T15:00:00+00:00',
            },
        }),
    },
};

/** From Linda's personal calendar, shared as busy only. */
export const BusyOnly: Story = {
    args: { event: calendarDay().data[3]! },
};
