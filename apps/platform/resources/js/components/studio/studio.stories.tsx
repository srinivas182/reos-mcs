import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import {
    type AdvertisingPage,
    AdvertisingPolicyView,
} from './advertising-policy';
import { generation, maker } from './fixtures';
import { Maker } from './maker';
import { Versions } from './versions';

const meta = {
    title: 'Studio/Make content',
    component: Maker,
    args: { data: maker(), onMake: fn() },
} satisfies Meta<typeof Maker>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Create a post (screen 165): from a listing, with the cost first. */
export const FromAListing: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('5 credits · 495 left'),
        ).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('checkbox', { name: 'Ad copy' }),
        );
        await userEvent.click(canvas.getByRole('button', { name: 'Make' }));
        await expect(args.onMake).toHaveBeenCalledWith({
            source: {
                type: 'listing',
                id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e66',
            },
            outputs: ['post', 'ad'],
        });
    },
};

/** A market update in the agent's words. */
export const MarketUpdate: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('button', { name: 'A market update' }),
        );
        await userEvent.type(
            canvas.getByLabelText("What's happening in your market?"),
            'Median price up 3% this month.',
        );
        await userEvent.type(canvas.getByLabelText('City'), 'Katy');
        await userEvent.click(canvas.getByRole('button', { name: 'Make' }));
        await expect(args.onMake).toHaveBeenCalledWith({
            source: {
                type: 'market',
                text: 'Median price up 3% this month.',
                city: 'Katy',
            },
            outputs: ['post'],
        });
    },
};

/** No listings to post yet. */
export const NoListings: Story = {
    args: { data: maker({ listings: [], sales: [] }) },
};

/** Pick a version (screen 166): designs, captions with tones, carousel and ad copy. */
export const PickAVersion: StoryObj<typeof Versions> = {
    render: (args) => <Versions {...args} />,
    args: { generation: generation(), onRetone: fn(), onUse: fn() },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('button', { name: 'Design 2' }));
        await userEvent.click(
            canvas.getAllByRole('button', { name: 'Español' })[0],
        );
        await expect(args.onRetone).toHaveBeenCalledWith(0, 'spanish');
        await userEvent.click(canvas.getByRole('button', { name: 'Use this' }));
        await expect(args.onUse).toHaveBeenCalledWith(
            generation().captions[0].text,
            [generation().designs[1]],
        );
    },
};

/** Advertising policy (broker web screen 35): approvals, Texas rules, brokerage rules, brand kit. */
export const AdvertisingPolicy: StoryObj<typeof AdvertisingPolicyView> = {
    render: (args) => <AdvertisingPolicyView {...args} />,
    args: {
        settingsHref: '/organisation/settings',
        onSave: fn(),
        onApprove: fn(),
        onSendBack: fn(),
        data: {
            brokerage: 'Lone Star Realty',
            texas: [
                {
                    key: 'advertising.license_holder_name',
                    text: 'Every advertisement shows the name of the license holder or team placing it, in a readily noticeable location.',
                    citation: '22 TAC §535.155(a)(1)',
                    url: 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-155',
                },
                {
                    key: 'advertising.broker_name_size',
                    text: "The broker's name is at least half the size of the largest contact information for any sales agent, associated broker or team name in the advertisement.",
                    citation: '22 TAC §535.155(a)(2)',
                    url: 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-155',
                },
            ],
            checks: { fair_housing: 21, claims: 7 },
            rules: {
                approval_new_agents: true,
                approval_all: false,
                banned: ['motivated seller'],
            },
            brand: { colour: '#1f3a5f', logo: null },
            pending: [
                {
                    id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e88',
                    agent: 'James Miller',
                    body: 'Open house Saturday, 1 to 3 pm at 2418 Willow Bend Dr.',
                    attribution: 'James Miller · Lone Star Realty',
                    media: [{ url: '/demo/listings/1.png', kind: 'image' }],
                    channels: ['facebook', 'instagram'],
                    scheduled_at: null,
                    requested_at: '2026-11-24T15:00:00Z',
                    issues: [],
                },
            ],
        },
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('button', { name: 'Approve' }));
        await expect(args.onApprove).toHaveBeenCalledWith(
            '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e88',
        );
        await userEvent.type(
            canvas.getByLabelText('Note to the agent'),
            'Add the price.',
        );
        await userEvent.click(
            canvas.getByRole('button', { name: 'Send back' }),
        );
        await expect(args.onSendBack).toHaveBeenCalledWith(
            '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e88',
            'Add the price.',
        );
    },
};

/** Nothing waiting. */
export const NothingWaiting: StoryObj<typeof AdvertisingPolicyView> = {
    render: (args) => <AdvertisingPolicyView {...args} />,
    args: {
        ...AdvertisingPolicy.args,
        data: {
            ...(AdvertisingPolicy.args?.data as AdvertisingPage),
            pending: [],
        },
    } as never,
};
