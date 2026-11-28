import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { BrokerageRules } from './brokerage-rules';
import { StaffPermissions } from './staff-permissions';

const routes = {
    check: '/storybook/organisation/policies/check',
    store: '/storybook/organisation/policies/rules',
    update: (id: number) => `/storybook/organisation/policies/rules/${id}`,
    destroy: (id: number) => `/storybook/organisation/policies/rules/${id}`,
    restore: (id: number) =>
        `/storybook/organisation/policies/rules/${id}/restore`,
};

const meta = {
    title: 'Brokerage/Policies',
    component: BrokerageRules,
    args: {
        canManage: true,
        routes,
        rules: [
            {
                id: 1,
                topic: 'Referral fees',
                text: 'Agents may accept referral fees directly from title companies.',
                conflict: {
                    rule: 'brokerage.compensation',
                    citation: '22 TAC §535.3',
                    url: 'https://www.trec.texas.gov/agency-information/rules-and-laws',
                    excerpt:
                        'Agents may accept referral fees directly from title companies.',
                    rule_text:
                        'A sales agent may accept compensation only from their sponsoring broker.',
                },
                updated_at: '2026-11-28T09:00:00-06:00',
            },
            {
                id: 2,
                topic: 'Listings',
                text: 'Send every new listing to the office before it goes live.',
                conflict: null,
                updated_at: '2026-11-27T09:00:00-06:00',
            },
        ],
    },
} satisfies Meta<typeof BrokerageRules>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Lone Star Realty's rules: one conflicts with TREC and shows its source. */
export const WithAConflict: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByText('Conflicts with 22 TAC §535.3'),
        ).toBeInTheDocument();
    },
};

/** No rules yet. */
export const NoRules: Story = { args: { rules: [] } };

/** An office admin sees the rules read-only. */
export const ReadOnly: Story = { args: { canManage: false } };

/** People and permissions: Linda, an office admin and a team leader. */
export const People: StoryObj<typeof StaffPermissions> = {
    render: () => (
        <StaffPermissions
            designated
            route={(id) => `/storybook/organisation/staff/${id}`}
            people={[
                {
                    user_id: 1,
                    name: 'Linda Park',
                    title: 'designated_broker',
                    team: null,
                    duties: [
                        'approve_posts',
                        'answer_escalations',
                        'manage_policies',
                        'sponsor_agents',
                    ],
                    editable: false,
                },
                {
                    user_id: 2,
                    name: 'Ana Ortiz',
                    title: 'office_admin',
                    team: null,
                    duties: ['approve_posts'],
                    editable: true,
                },
                {
                    user_id: 3,
                    name: 'Chidi Okoye',
                    title: 'team_leader',
                    team: 'Okoye Team',
                    duties: [],
                    editable: true,
                },
            ]}
        />
    ),
};
