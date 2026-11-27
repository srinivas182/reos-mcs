import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { MadePostEditor, MomentList } from './candidate-studio';
import { madeFixture, momentsFixture } from './fixtures';

const meta = {
    title: 'Studio/Candidate studio',
    component: MomentList,
    args: { moments: momentsFixture(), onOpen: fn() },
} satisfies Meta<typeof MomentList>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Study wins ready to post (B13.3). */
export const Moments: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('button', { name: 'Make the post' }),
        );
        await expect(args.onOpen).toHaveBeenCalled();
        await expect(
            canvas.getByRole('button', { name: 'Open the post' }),
        ).toBeInTheDocument();
    },
};

/** Nothing yet. */
export const NoMoments: Story = {
    args: { moments: [] },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Nothing to post yet'),
        ).toBeInTheDocument();
    },
};

/** A pass announcement: an edited caption that offers services is blocked. */
export const Blocked: StoryObj<typeof MadePostEditor> = {
    render: (args) => <MadePostEditor {...args} />,
    args: {
        post: madeFixture(),
        targets: [
            {
                connection: 1,
                channel: 'facebook',
                id: 'p',
                name: 'Jordan Reyes',
            },
        ],
        onCheck: fn(async () => ({
            ok: false,
            issues: [
                { phrase: 'call me', citation: 'Tex. Occ. Code §1101.351' },
            ],
        })),
        onPost: fn(),
        onCopy: fn(),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        const box = canvas.getByLabelText('Caption');
        await userEvent.type(box, ' Call me to sell your home!');
        await userEvent.click(canvas.getByRole('button', { name: 'Check' }));
        await expect(
            await canvas.findByText(
                '"call me" offers real estate services before your license is active (Tex. Occ. Code §1101.351).',
            ),
        ).toBeInTheDocument();
        await expect(
            canvas.getByRole('button', { name: 'Post now' }),
        ).toBeDisabled();
        await expect(args.onPost).not.toHaveBeenCalled();
    },
};
