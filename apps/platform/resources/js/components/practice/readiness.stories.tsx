import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { readinessMap } from './fixtures';
import { ReadinessView } from './readiness-view';

const meta = {
    title: 'Candidate/Readiness',
    component: ReadinessView,
    args: { map: readinessMap },
} satisfies Meta<typeof ReadinessView>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screens 54 and 44: both portions against the 90% line, what booking needs, topics weakest first. */
export const NearlyThere: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('6 points to the booking line'),
        ).toBeInTheDocument();
    },
};

/** Both portions at 90% and two simulations passed: book with the testing vendor (screen 56). */
export const ReadyToBook: Story = {
    args: {
        map: {
            ...readinessMap,
            percent: 92,
            portions: { national: 92, state: 95 },
            booking: {
                ...readinessMap.booking,
                ready: true,
                national: true,
                simulations: 2,
            },
        },
    },
};

/** Before any test. */
export const NotYet: Story = {
    args: {
        map: {
            ...readinessMap,
            percent: null,
            portions: { national: null, state: null },
            topics: readinessMap.topics.map((x) => ({
                ...x,
                percent: null,
                answers: 0,
                shown: false,
                trend: null,
                weak: false,
            })),
            gain: null,
            computed_on: null,
        },
    },
};
