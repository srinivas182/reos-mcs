import type { Meta, StoryObj } from '@storybook/react-vite';
import { calendarDay, calendarEvent } from '@/components/calendar-fixtures';
import { TodayCard } from '@/components/today-card';

/** "Today" on the agent dashboard (web journey screen 12; B4.1). */
const meta = {
    title: 'Calendar/Today card',
    component: TodayCard,
    args: { today: calendarDay() },
} satisfies Meta<typeof TodayCard>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Linda's Saturday: an option-period deadline, a call from Google, a showing and a busy slot. */
export const Day: Story = {};

/** A showing changed in REOS and in Google; REOS's change was newer. */
export const ChangedInTwoPlaces: Story = {
    args: {
        today: calendarDay({
            data: [
                calendarEvent({
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
                }),
                calendarEvent({
                    id: 44,
                    title: 'Buyer consultation — Ana Torres',
                    kind: 'appointment',
                    sync: 'failed',
                    starts_at: '2026-11-21T22:00:00+00:00',
                    ends_at: '2026-11-21T23:00:00+00:00',
                }),
            ],
        }),
    },
};

/** No calendar connected and nothing today. */
export const Empty: Story = {
    args: { today: calendarDay({ data: [], write_to: null }) },
};
