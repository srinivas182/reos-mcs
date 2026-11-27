import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { CampaignForm, CampaignList, ModeTargetsForm } from './broker-rewards';
import { DailyGoalsCard } from './daily-goals';
import {
    campaignsFixture,
    modesFixture,
    rewardsFixture,
    todayFixture,
} from './fixtures';
import { RewardsPanel } from './rewards-panel';

const meta = {
    title: 'Rewards/Daily goals',
    component: DailyGoalsCard,
    args: { today: todayFixture(), onShare: fn() },
} satisfies Meta<typeof DailyGoalsCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Today's goals, points, streak and Share (screen 111). */
export const Today: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('4-day streak')).toBeInTheDocument();
        await expect(canvas.getByText('+25 bonus')).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('button', { name: 'Share my streak' }),
        );
        await expect(args.onShare).toHaveBeenCalledWith(
            "I'm on a 4-day streak of meeting my daily goals on REOS.",
        );
    },
};

/** No streak yet. */
export const NoStreak: Story = {
    args: { today: todayFixture({ streak: 0, best_streak: 0, campaigns: [] }) },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText(
                'Meet every goal today to start a streak.',
            ),
        ).toBeInTheDocument();
    },
};

/** The balance, what points pay for and recent points (screen 112). */
export const Rewards: StoryObj<typeof RewardsPanel> = {
    render: (args) => <RewardsPanel {...args} />,
    args: {
        rewards: rewardsFixture(),
        mentorsHref: '/mentors',
        onRedeem: fn(),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('Daily goal: A CE lesson'),
        ).toBeInTheDocument();
        await expect(
            canvas.getByRole('button', { name: 'Not enough points yet' }),
        ).toBeDisabled();
        await userEvent.click(
            canvas.getByRole('button', { name: 'Use 1500 points' }),
        );
        await expect(args.onRedeem).toHaveBeenCalled();
    },
};

/** Nothing earned yet. */
export const NoPoints: StoryObj<typeof RewardsPanel> = {
    render: (args) => <RewardsPanel {...args} />,
    args: {
        rewards: rewardsFixture({
            balance: 0,
            value_cents: 0,
            options: [],
            history: [],
        }),
        onRedeem: fn(),
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('No points yet'),
        ).toBeInTheDocument();
    },
};

/** The broker's daily goals for a mode. */
export const BrokerTargets: StoryObj<typeof ModeTargetsForm> = {
    render: (args) => <ModeTargetsForm {...args} />,
    args: {
        mode: 'launch',
        value: modesFixture().launch,
        goals: ['conversations', 'ce_lesson', 'follow_ups', 'post'],
        onSave: fn(),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        const input = canvas.getByLabelText('Conversations');
        await userEvent.clear(input);
        await userEvent.type(input, '8');
        await userEvent.click(canvas.getByRole('button', { name: 'Save' }));
        await expect(args.onSave).toHaveBeenCalledWith(
            'launch',
            expect.objectContaining({ conversations: 8 }),
        );
    },
};

/** Bonus campaigns: a new one and the list. */
export const Campaigns: StoryObj<typeof CampaignList> = {
    render: (args) => (
        <div className="space-y-4">
            <CampaignForm
                goals={['conversations', 'ce_lesson', 'follow_ups', 'post']}
                multipliers={[2, 3]}
                onCreate={fn()}
            />
            <CampaignList {...args} />
        </div>
    ),
    args: { campaigns: campaignsFixture(), onEnd: fn() },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('6,250 of 50,000 points used'),
        ).toBeInTheDocument();
        await userEvent.click(canvas.getByRole('button', { name: 'End now' }));
        await expect(args.onEnd).toHaveBeenCalled();
    },
};

/** No campaigns yet. */
export const NoCampaigns: StoryObj<typeof CampaignList> = {
    render: (args) => <CampaignList {...args} />,
    args: { campaigns: [], onEnd: fn() },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('No bonus campaigns yet'),
        ).toBeInTheDocument();
    },
};
