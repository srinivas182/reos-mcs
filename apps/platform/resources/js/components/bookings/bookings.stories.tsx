import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { BookingRow } from './booking-row';
import { anaBooking, nelle } from './fixtures';
import { InstructorCard } from './instructor-card';

const meta = {
    title: 'Candidate/Book an instructor',
    component: InstructorCard,
    args: { offer: nelle },
} satisfies Meta<typeof InstructorCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 18: Nelle Hart, 4.9 from 38 sessions, from $35, English and Spanish. */
export const Instructor: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('From $35')).toBeInTheDocument();
    },
};

/** Included sessions (a school's own students). */
export const Included: Story = {
    args: {
        offer: { ...nelle, price_30: 0, price_60: 0, rating: null, ratings: 0 },
    },
};

/** Ana's booked session: join, or cancel with a refund until 24 hours before. */
export const Booked: Story = {
    render: () => (
        <ul>
            <BookingRow
                booking={anaBooking}
                onCancel={() => undefined}
                onRate={() => undefined}
            />
        </ul>
    ),
};

/** After the session: rate it. */
export const ToRate: Story = {
    render: () => (
        <ul>
            <BookingRow
                booking={{
                    ...anaBooking,
                    status: 'completed',
                    can_cancel: false,
                    can_rate: true,
                }}
                onCancel={() => undefined}
                onRate={() => undefined}
            />
        </ul>
    ),
};
