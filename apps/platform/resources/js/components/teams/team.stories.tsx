import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { LeadRoutingPanel, SplitSheetsPanel, TeamLeads } from './team';
import {
    okoyeRouting,
    okoyeSplits,
    priyaOffered,
    priyaSheet,
} from './team-fixtures';

const meta = {
    title: 'Teams/Lead routing and split sheets',
    component: LeadRoutingPanel,
    args: {
        board: okoyeRouting,
        routes: { rules: '#', member: () => '#', lead: '#' },
    },
} satisfies Meta<typeof LeadRoutingPanel>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Okoye Team's routing: first to claim in 5 minutes. */
export const Routing: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('button', { name: 'Save rules' }),
        ).toBeEnabled();
    },
};

/** No leads yet. */
export const NoLeads: Story = {
    args: { board: { ...okoyeRouting, leads: [] } },
};

/** The team agreement and two split sheets. */
export const SplitSheets: Story = {
    render: () => (
        <SplitSheetsPanel
            board={okoyeSplits}
            routes={{ agreement: '#', send: '#' }}
        />
    ),
};

/** Priya: one lead to claim and one sheet to sign. */
export const AgentLeads: Story = {
    render: () => (
        <TeamLeads
            offered={priyaOffered}
            splits={[priyaSheet]}
            routes={{ claim: () => '#', sign: () => '#', question: () => '#' }}
        />
    ),
};

/** Nothing to claim or sign. */
export const AgentNothing: Story = {
    render: () => (
        <TeamLeads
            offered={[]}
            splits={[]}
            routes={{ claim: () => '#', sign: () => '#', question: () => '#' }}
        />
    ),
};
