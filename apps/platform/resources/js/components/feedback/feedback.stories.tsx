import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { MyFeedbackPanel, ToRateList } from './feedback-view';
import { mineFixture, viewFixture } from './fixtures';

const meta = {
    title: 'Feedback/From agents',
    component: MyFeedbackPanel,
    args: {
        mine: mineFixture(),
        onPublic: fn(),
        onRespond: fn(),
        onReport: fn(),
    },
} satisfies Meta<typeof MyFeedbackPanel>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Three or more ratings: averages, a tip and comments without names (screen 125). */
export const Shown: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('4.2 out of 5 from 4 agents'),
        ).toBeInTheDocument();
        await userEvent.click(
            canvas.getAllByRole('button', { name: 'Respond' })[0],
        );
        await userEvent.type(
            canvas.getByLabelText('Your response'),
            'Thanks, I will share reports sooner.',
        );
        await userEvent.click(
            canvas.getByRole('button', { name: 'Save response' }),
        );
        await expect(args.onRespond).toHaveBeenCalled();
    },
};

/** Fewer than three ratings: nothing shows yet. */
export const Waiting: Story = {
    args: {
        mine: mineFixture({
            count: 2,
            shown: false,
            average: null,
            items: [],
            tip: null,
        }),
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText(
                '2 agents have rated you. Your feedback shows once 3 have.',
            ),
        ).toBeInTheDocument();
    },
};

/** Rate the other side's agent. */
export const Rate: StoryObj<typeof ToRateList> = {
    render: (args) => <ToRateList {...args} />,
    args: { rows: viewFixture().to_rate, onSend: fn() },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        const send = canvas.getByRole('button', { name: 'Send feedback' });
        await expect(send).toBeDisabled();
        for (const group of canvas.getAllByRole('radiogroup')) {
            await userEvent.click(
                within(group).getByRole('radio', { name: '4 stars' }),
            );
        }
        await userEvent.click(send);
        await expect(args.onSend).toHaveBeenCalledWith(
            expect.objectContaining({ agent: { id: 21, name: 'Priya Shah' } }),
            expect.objectContaining({ communication: 4, professionalism: 4 }),
        );
    },
};

/** No one to rate. */
export const NoneToRate: StoryObj<typeof ToRateList> = {
    render: (args) => <ToRateList {...args} />,
    args: { rows: [], onSend: fn() },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('No one to rate right now'),
        ).toBeInTheDocument();
    },
};
