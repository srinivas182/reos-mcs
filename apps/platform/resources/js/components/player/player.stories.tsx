import type { Meta, StoryObj } from '@storybook/react-vite';
import { CheckinDialog } from './checkin-dialog';
import {
    counting,
    done,
    nearLimit,
    paused,
    presence,
    question,
} from './fixtures';
import { SeatMeter } from './seat-meter';

const meta = {
    title: 'Learn/Course player',
    component: SeatMeter,
    args: { state: counting },
} satisfies Meta<typeof SeatMeter>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 5: "1 h 12 m of 2 h counted". */
export const Counting: Story = {};

/** Why the last stretch didn't count, in plain words. */
export const Paused: Story = { args: { state: paused } };

/** The approval's daily maximum is close. */
export const NearDailyLimit: Story = { args: { state: nearLimit } };

export const Complete: Story = { args: { state: done } };

export const Offline: Story = { args: { failed: true } };

const answer = async () => ({
    correct: false,
    explanation:
        'The IABS notice is given at the first substantive communication with a prospective buyer, seller, landlord or tenant.',
    source: 'TREC Rule §531.20',
    state: counting,
});

export const QuestionCheckin: Story = {
    render: () => <CheckinDialog checkpoint={question} onAnswer={answer} />,
};

export const PresenceCheckin: Story = {
    render: () => <CheckinDialog checkpoint={presence} onAnswer={answer} />,
};
