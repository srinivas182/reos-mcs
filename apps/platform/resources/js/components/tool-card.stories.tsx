import type { Meta, StoryObj } from '@storybook/react-vite';
import { ToolCard } from '@/components/tool-card';
import { calendarConnection, tool } from '@/components/tool-fixtures';

/** One tool on Connections (web journey screen 19), in each state. */
const meta = {
    title: 'Connections/Tool card',
    component: ToolCard,
    parameters: { layout: 'padded' },
    decorators: [
        (Story) => (
            <div className="max-w-sm">
                <Story />
            </div>
        ),
    ],
    args: { tool: tool() },
} satisfies Meta<typeof ToolCard>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Ready to connect: opens Google's own sign-in. */
export const Available: Story = {};

/** James Miller's calendar, connected. */
export const Connected: Story = {
    args: { tool: tool({ connected: 1 }), connection: calendarConnection() },
};

/** Google stopped accepting REOS's access. */
export const NeedsSignIn: Story = {
    args: {
        tool: tool({ connected: 1 }),
        connection: calendarConnection({ status: 'reconnect_needed' }),
    },
};

export const Beta: Story = {
    args: {
        tool: tool({
            id: 'linkedin',
            name: 'LinkedIn',
            category: 'social',
            status: 'beta',
            action: 'join_beta',
            purpose: 'Post to your profile and company page.',
        }),
    },
};

export const ComingSoon: Story = {
    args: {
        tool: tool({
            id: 'follow-up-boss',
            name: 'Follow Up Boss',
            category: 'crm',
            status: 'coming_soon',
            action: 'notify',
            purpose: 'Contacts, notes and tasks, both ways.',
        }),
    },
};

/** Notify me was tapped. */
export const ComingSoonNoted: Story = {
    args: {
        tool: tool({
            id: 'lofty',
            name: 'Lofty',
            category: 'crm',
            status: 'coming_soon',
            action: 'notify',
            interested: true,
            purpose: 'Contacts, notes and tasks, both ways.',
        }),
    },
};

/** The super admin's kill switch is on. */
export const TemporarilyUnavailable: Story = {
    args: {
        tool: tool({
            id: 'lofty',
            name: 'Lofty',
            category: 'crm',
            status: 'paused',
            action: 'none',
            purpose: 'Contacts, notes and tasks, both ways.',
        }),
    },
};

/** Connected once by the brokerage, for every agent. */
export const SetUpByBrokerage: Story = {
    args: {
        tool: tool({
            id: 'boldtrail-backoffice',
            name: 'BoldTrail BackOffice',
            category: 'transactions',
            accounts: 'tenant',
            purpose: 'Deals and commissions. Usually set up by your broker.',
        }),
    },
};

/** A tool the brokerage sets up once: the agent asks their broker (A4.3a). */
export const AskMyBroker: Story = {
    args: {
        tool: tool({
            id: 'lone-wolf',
            name: 'Lone Wolf Transactions',
            category: 'transactions',
            status: 'coming_soon',
            action: 'ask_broker',
            accounts: 'tenant',
            purpose: 'Deals, documents and commissions from the back office.',
        }),
    },
};

/** Asked: the broker heard about it. */
export const AskedMyBroker: Story = {
    args: {
        tool: tool({
            id: 'lone-wolf',
            name: 'Lone Wolf Transactions',
            category: 'transactions',
            status: 'coming_soon',
            action: 'ask_broker',
            accounts: 'tenant',
            interested: true,
            purpose: 'Deals, documents and commissions from the back office.',
        }),
    },
};
