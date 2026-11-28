import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { InviteCard, ReferralsView } from './referrals-view';
import type { Referral } from './types';

const elena: Referral = {
    id: 'r1',
    direction: 'received',
    status: 'sent',
    kind: 'buyer',
    client: {
        name: 'Elena Torres',
        email: 'elena@example.com',
        phone: null,
    },
    area: 'Austin',
    note: 'Relocating in March; two kids.',
    fee_percent: 25,
    from: { name: 'Maria Garcia', broker: 'Lone Star Realty' },
    to: {
        name: 'Dev Shah',
        email: 'dev.shah@example.com',
        broker: 'Hill Country Homes',
        on_reos: true,
    },
    contact: null,
    sent_at: '2026-11-28T10:00:00-06:00',
    answered_at: null,
    closed_at: null,
};

const routes = {
    create: '/storybook/referrals',
    accept: (id: string) => `/storybook/referrals/${id}/accept`,
    decline: (id: string) => `/storybook/referrals/${id}/decline`,
    close: (id: string) => `/storybook/referrals/${id}/close`,
    contact: (uuid: string) => `/contacts?contact=${uuid}`,
};

const meta = {
    title: 'Leads/Referrals',
    component: ReferralsView,
    args: { referrals: { sent: [], received: [] }, routes },
} satisfies Meta<typeof ReferralsView>;

export default meta;

type Story = StoryObj<typeof meta>;

/** No referrals yet: the form and a calm empty state. */
export const FirstReferral: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByText('No referrals yet'),
        ).toBeInTheDocument();
    },
};

/** Maria in Houston refers a buyer moving to Austin to Dev. */
export const ReferredToYou: Story = {
    args: { referrals: { sent: [], received: [elena] }, routes },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByRole('button', { name: 'Accept' }),
        ).toBeInTheDocument();
        await expect(
            canvas.getByText(/From Maria Garcia, Lone Star Realty/),
        ).toBeInTheDocument();
    },
};

/** Invite and earn, with one person licensed. */
export const InviteAndEarn: StoryObj<typeof InviteCard> = {
    render: () => (
        <InviteCard
            invite={{
                code: 'K7M2XQ9P',
                link: 'https://reos.com/join/K7M2XQ9P',
                points_each: 2500,
                earned: 2500,
                invited: [
                    {
                        name: 'Ana R.',
                        joined_at: '2026-10-02T10:00:00-05:00',
                        licensed: true,
                    },
                    {
                        name: 'Sam L.',
                        joined_at: '2026-11-20T10:00:00-06:00',
                        licensed: false,
                    },
                ],
                invited_by: null,
                can_claim: false,
            }}
        />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByText(/Earned: 2,500 points/),
        ).toBeInTheDocument();
    },
};
