import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { CoursePicks } from './course-picks';
import { picksFixture } from './course-picks.fixtures';

const meta = {
    title: 'Learn/Course picks',
    component: CoursePicks,
    args: {
        picks: picksFixture(),
        lessonHref: (id: number) => `/learn/lessons/${id}`,
    },
} satisfies Meta<typeof CoursePicks>;

export default meta;

type Story = StoryObj<typeof meta>;

/** CE first before renewal, then the skills practice found weak, then the agent's stage. */
export const Picked: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Picked for you')).toBeInTheDocument();
        await expect(
            canvas.getByText(
                'To work on Asking for the next step, from your practice.',
            ),
        ).toBeInTheDocument();
        await expect(
            canvas.getAllByText('Can listen on your phone'),
        ).toHaveLength(2);
    },
};
