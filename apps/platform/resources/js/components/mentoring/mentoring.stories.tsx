import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { program } from './fixtures';
import { AgentAssignments, MentorList } from './mentor-program';

const meta = {
    title: 'Mentoring/Mentor program',
    component: MentorList,
    args: { data: program(), onSave: fn() },
} satisfies Meta<typeof MentorList>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Mentors with focus, load, price and rating; open Settings and save (screens 21 and 22). */
export const Mentors: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Mentees: 4 of 4')).toBeInTheDocument();
        await userEvent.click(
            canvas.getAllByRole('button', { name: 'Settings' })[1],
        );
        await userEvent.click(canvas.getByLabelText('Accept reward points'));
        await userEvent.click(canvas.getByRole('button', { name: 'Save' }));
        await expect(args.onSave).toHaveBeenCalledWith(
            expect.objectContaining({ id: 12 }),
            expect.objectContaining({ points: false, price_60_cents: 7500 }),
        );
    },
};

/** No mentors yet. */
export const NoMentors: Story = {
    args: { data: program({ mentors: [] }) },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('No mentors yet'),
        ).toBeInTheDocument();
    },
};

/** Assign a mentor with the best matches and their reasons (screen 23). */
export const Assign: Story = {
    render: () => (
        <AgentAssignments
            agents={program().agents}
            onMatches={async () => [
                {
                    id: 11,
                    name: 'Sarah Kim',
                    score: 7,
                    reasons: ['language', 'focus', 'capacity'],
                    room: 2,
                },
            ]}
            onAssign={fn()}
        />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('button', { name: 'Assign' }));
        await expect(
            await canvas.findByText(
                /Speaks their language · Fits their goal · Has room · 2 places left/,
            ),
        ).toBeInTheDocument();
        await expect(
            canvas.getByText(/Asked for a change/),
        ).toBeInTheDocument();
    },
};
