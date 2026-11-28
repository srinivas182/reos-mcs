import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { TrecRoster } from './trec-roster';

const meta = {
    title: 'Brokerage/Bring in your agents',
    component: TrecRoster,
    args: {
        routes: {
            contacts: '/storybook/organisation/roster/contacts',
            invite: '/storybook/organisation/roster/invite',
        },
        roster: {
            trec_number: '0600123',
            counts: {
                sponsored: 3,
                member: 1,
                invited: 0,
                on_reos: 1,
                contact: 0,
                needs_contact: 1,
            },
            agents: [
                {
                    licence: '0722222',
                    name: 'Maria Lopez',
                    type: 'SALE',
                    expires_on: '2027-01-31',
                    state: 'needs_contact',
                    contact: null,
                },
                {
                    licence: '0733333',
                    name: 'Omar Haddad',
                    type: 'SALE',
                    expires_on: '2027-05-31',
                    state: 'on_reos',
                    contact: null,
                },
                {
                    licence: '0744444',
                    name: 'Priya Nair',
                    type: 'SALE',
                    expires_on: '2027-09-30',
                    state: 'member',
                    contact: null,
                },
            ],
        },
    },
} satisfies Meta<typeof TrecRoster>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Bayou City Homes' TREC roster: one in, one on REOS, one needing an email. */
export const BayouCityHomes: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByRole('button', { name: 'Invite 1 agent' }),
        ).toBeInTheDocument();
    },
};

/** A new brokerage TREC lists no agents under yet. */
export const NoAgentsYet: Story = {
    args: {
        roster: {
            trec_number: '9033333',
            counts: {
                sponsored: 0,
                member: 0,
                invited: 0,
                on_reos: 0,
                contact: 0,
                needs_contact: 0,
            },
            agents: [],
        },
    },
};
