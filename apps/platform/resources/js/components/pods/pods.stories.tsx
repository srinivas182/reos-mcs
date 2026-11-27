import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { ChallengeForm } from './challenge-form';
import { challengeRows, podView } from './fixtures';
import { ChallengeList, PodPanel } from './pod-view';

const meta = {
    title: 'Pods/Accountability pod',
    component: PodPanel,
    args: {
        pod: podView(),
        callHref: '/sessions/1',
        messagesHref: '/messages?c=1',
        onJoin: fn(),
        onLeave: fn(),
        onGoal: fn(),
    },
} satisfies Meta<typeof PodPanel>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A pod of five with goals and shared numbers; save this week's goal (screen 131). */
export const Member: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Sofia Ramirez')).toBeInTheDocument();
        const input = canvas.getByLabelText('Your goal this week');
        await userEvent.clear(input);
        await userEvent.type(input, '25 conversations');
        await userEvent.click(
            canvas.getByRole('button', { name: 'Save goal' }),
        );
        await expect(args.onGoal).toHaveBeenCalledWith('25 conversations');
    },
};

/** Not in a pod yet. */
export const NotJoined: Story = {
    args: { pod: { status: 'none', waiting_since: null, pod: null } },
    play: async ({ canvasElement, args }) => {
        await userEvent.click(
            within(canvasElement).getByRole('button', { name: 'Join a pod' }),
        );
        await expect(args.onJoin).toHaveBeenCalled();
    },
};

/** Waiting to be matched. */
export const Waiting: Story = {
    args: {
        pod: {
            status: 'waiting',
            waiting_since: '2026-11-25T10:00:00Z',
            pod: null,
        },
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Finding your pod'),
        ).toBeInTheDocument();
    },
};

/** Brokerage challenges with total and most improved boards. */
export const Challenges: StoryObj<typeof ChallengeList> = {
    render: (args) => <ChallengeList {...args} />,
    args: { challenges: challengeRows(), onJoin: fn(), onLeave: fn() },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getAllByText('Personal best').length,
        ).toBeGreaterThan(0);
        await userEvent.click(canvas.getByRole('button', { name: 'Join' }));
        await expect(args.onJoin).toHaveBeenCalled();
    },
};

/** No challenges yet. */
export const NoChallenges: StoryObj<typeof ChallengeList> = {
    render: (args) => <ChallengeList {...args} />,
    args: { challenges: [] },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('No challenges right now'),
        ).toBeInTheDocument();
    },
};

/** The broker's new-challenge form. */
export const NewChallenge: StoryObj<typeof ChallengeForm> = {
    render: (args) => <ChallengeForm {...args} />,
    args: {
        metrics: ['conversations', 'appointments', 'ce_hours', 'posts'],
        onCreate: fn(),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.type(
            canvas.getByLabelText('Name'),
            'December conversations',
        );
        await userEvent.type(canvas.getByLabelText('Starts'), '2026-12-01');
        await userEvent.type(canvas.getByLabelText('Ends'), '2026-12-31');
        await userEvent.click(
            canvas.getByRole('button', { name: 'Create challenge' }),
        );
        await expect(args.onCreate).toHaveBeenCalled();
    },
};
